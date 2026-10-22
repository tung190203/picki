<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ConversationUpdated;
use App\Jobs\SendPushJob;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatRealtimeAndPushTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userA;
    protected User $userB;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['full_name' => 'Player A']);
        $this->userB = User::factory()->create(['full_name' => 'Player B']);

        $res = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);

        $this->conversation = Conversation::find($res->json('data.id'));
    }

    public function test_send_message_dispatches_events_and_push_job(): void
    {
        Event::fake([ChatMessageSent::class, ConversationUpdated::class]);
        Queue::fake([SendPushJob::class]);

        $res = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$this->conversation->id}/messages", [
                'type' => 'text',
                'content' => 'Xin chào realtime!',
            ]);

        $res->assertStatus(200);

        // 1. Kiểm tra sự kiện ChatMessageSent phát sóng tới phòng chat
        Event::assertDispatched(ChatMessageSent::class, function ($event) {
            return $event->conversationId === $this->conversation->id
                && $event->message->content === 'Xin chào realtime!';
        });

        // 2. Kiểm tra sự kiện ConversationUpdated phát sóng tới người nhận
        Event::assertDispatched(ConversationUpdated::class, function ($event) {
            return $event->userId === $this->userB->id;
        });

        // 3. Kiểm tra Job SendPushJob gửi thông báo FCM tới người nhận
        Queue::assertPushed(SendPushJob::class, function ($job) {
            return $job->userId === $this->userB->id
                && $job->body === 'Xin chào realtime!'
                && isset($job->data['action']);
        });
    }

    public function test_muted_participant_does_not_receive_push(): void
    {
        Queue::fake([SendPushJob::class]);

        // Mute người nhận B
        ConversationParticipant::where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->userB->id)
            ->update(['is_muted' => true]);

        $res = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$this->conversation->id}/messages", [
                'type' => 'text',
                'content' => 'Tin nhắn khi bị mute',
            ]);

        $res->assertStatus(200);

        // Không gửi Push cho B vì đã tắt thông báo
        Queue::assertNotPushed(SendPushJob::class, function ($job) {
            return $job->userId === $this->userB->id;
        });
    }

    public function test_channel_authorization(): void
    {
        $nonParticipant = User::factory()->create(['full_name' => 'Stranger']);

        // 1. Participant có quyền vào channel
        $this->assertTrue(
            ConversationParticipant::where('conversation_id', $this->conversation->id)
                ->where('user_id', $this->userA->id)
                ->exists()
        );

        // 2. Non-participant không có quyền vào channel
        $this->assertFalse(
            ConversationParticipant::where('conversation_id', $this->conversation->id)
                ->where('user_id', $nonParticipant->id)
                ->exists()
        );
    }
}

<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\UserBlock;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Services\Chat\ChatAntiSpamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChatDmAndStrangerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create([
            'full_name' => 'User A',
            'email' => 'usera_' . uniqid() . '@example.com',
        ]);

        $this->userB = User::factory()->create([
            'full_name' => 'User B',
            'email' => 'userb_' . uniqid() . '@example.com',
        ]);
    }

    public function test_can_start_dm_with_stranger(): void
    {
        $response = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.type', 'dm')
            ->assertJsonPath('data.is_stranger', false) // userA là người chủ động nhắn nên không coi là người lạ
            ->assertJsonPath('data.context_tag', 'Không có điểm chung — tìm thấy qua Tìm kiếm người chơi');

        // Đối với user B, hội thoại này phải là is_stranger = true
        $conversationId = $response->json('data.id');
        $conversation = Conversation::find($conversationId);
        $participantB = $conversation->participants()->where('user_id', $this->userB->id)->first();

        $this->assertTrue($participantB->is_stranger);
        $this->assertFalse($participantB->has_replied);
    }

    public function test_can_identify_shared_club_as_acquaintance(): void
    {
        $club = Club::factory()->create([
            'name' => 'Du Già MC',
        ]);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->userA->id,
            'role' => 'member',
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->userB->id,
            'role' => 'member',
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.context_tag', 'Cùng CLB Du Già MC');

        $conversationId = $response->json('data.id');
        $conversation = Conversation::find($conversationId);
        $participantB = $conversation->participants()->where('user_id', $this->userB->id)->first();

        // Cùng CLB nên không phải người lạ
        $this->assertFalse($participantB->is_stranger);
    }

    public function test_send_message_and_reply_converts_stranger_to_acquaintance(): void
    {
        // 1. User A tạo DM với User B (người lạ)
        $dmRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);
        $conversationId = $dmRes->json('data.id');

        // 2. User A gửi tin nhắn đầu tiên
        $sendRes = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'type' => 'text',
                'content' => 'Xin chào bạn B!',
            ]);
        $sendRes->assertStatus(200)
            ->assertJsonPath('data.content', 'Xin chào bạn B!');

        // Check unread count của B tăng lên 1
        $conversation = Conversation::find($conversationId);
        $participantB = $conversation->participants()->where('user_id', $this->userB->id)->first();
        $this->assertEquals(1, $participantB->unread_count);
        $this->assertTrue($participantB->is_stranger);

        // 3. User B trả lời lại (Reply lần đầu tiên)
        $replyRes = $this->actingAs($this->userB, 'api')
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'type' => 'text',
                'content' => 'Chào bạn A, mình đây!',
            ]);
        $replyRes->assertStatus(200);

        // 4. Kiểm tra quy tắc sản phẩm: Hội thoại chuyển vĩnh viễn khỏi mục Người lạ (is_stranger = false)
        $participantB->refresh();
        $this->assertFalse($participantB->is_stranger);
        $this->assertTrue($participantB->has_replied);
    }

    public function test_anti_spam_limit_to_strangers(): void
    {
        $dmRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);
        $conversationId = $dmRes->json('data.id');

        // Gửi liên tục 10 tin nhắn
        for ($i = 1; $i <= 10; $i++) {
            $res = $this->actingAs($this->userA, 'api')
                ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                    'type' => 'text',
                    'content' => "Tin nhắn thứ {$i}",
                ]);
            $res->assertStatus(200);
        }

        // Tin thứ 11 phải bị chặn 429
        $res11 = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'type' => 'text',
                'content' => 'Tin nhắn thứ 11',
            ]);

        $res11->assertStatus(429);
        $this->assertStringContainsString('10/10 tin nhắn', $res11->json('message'));
    }

    public function test_user_block_and_unblock(): void
    {
        // 1. User B chặn User A
        $blockRes = $this->actingAs($this->userB, 'api')
            ->postJson("/api/chat/users/{$this->userA->id}/block", [
                'reason' => 'Spam làm phiền',
            ]);
        $blockRes->assertStatus(200);

        // 2. User A cố gắng tạo chat hoặc gửi tin nhắn -> bị chặn 400
        $dmRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);
        $dmRes->assertStatus(400);

        // 3. User B bỏ chặn
        $unblockRes = $this->actingAs($this->userB, 'api')
            ->deleteJson("/api/chat/users/{$this->userA->id}/unblock");
        $unblockRes->assertStatus(200);

        // 4. Giờ có thể tạo chat bình thường
        $dmRes2 = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);
        $dmRes2->assertStatus(200);
    }

    public function test_can_report_user_in_chat(): void
    {
        $reportRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/reports', [
                'reported_user_id' => $this->userB->id,
                'reason' => 'Nội dung quấy rối',
                'details' => 'Gửi tin nhắn spam liên tục',
            ]);

        $reportRes->assertStatus(201)
            ->assertJsonPath('status', true);
    }

    public function test_message_read_and_status_tracking(): void
    {
        // 1. Tạo DM giữa userA và userB
        $dmRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);
        $conversationId = $dmRes->json('data.id');

        // 2. User A gửi tin nhắn -> Trạng thái ban đầu là 'sent' (Đã gửi)
        $sendRes = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$conversationId}/messages", [
                'type' => 'text',
                'content' => 'Kiểm tra trạng thái đã xem',
            ]);
        $sendRes->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        // 3. User B đọc tin nhắn (gọi get messages hoặc mark read)
        $this->actingAs($this->userB, 'api')
            ->postJson("/api/chat/conversations/{$conversationId}/read")
            ->assertStatus(200);

        // 4. User A kiểm tra lại tin nhắn -> Trạng thái phải chuyển thành 'seen' (Đã xem)
        $msgListRes = $this->actingAs($this->userA, 'api')
            ->getJson("/api/chat/conversations/{$conversationId}/messages");

        $msgListRes->assertStatus(200);
        $firstMsg = collect($msgListRes->json('data'))->firstWhere('content', 'Kiểm tra trạng thái đã xem');
        $this->assertNotNull($firstMsg);
        $this->assertEquals('seen', $firstMsg['status']);
    }
}


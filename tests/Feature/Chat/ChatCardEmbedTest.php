<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChatCardEmbedTest extends TestCase
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

        $dmRes = $this->actingAs($this->userA, 'api')
            ->postJson('/api/chat/conversations/dm', [
                'receiver_id' => $this->userB->id,
            ]);

        $this->conversation = Conversation::find($dmRes->json('data.id'));
    }

    public function test_send_and_resolve_match_card(): void
    {
        $mini = MiniTournament::create([
            'name' => 'Kèo Pickleball Tối Thứ 6',
            'created_by' => $this->userA->id,
            'sport_id' => 1,
            'max_players' => 12,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
        ]);

        MiniParticipant::create([
            'mini_tournament_id' => $mini->id,
            'user_id' => $this->userA->id,
            'is_confirmed' => 1,
        ]);

        $res = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$this->conversation->id}/messages", [
                'type' => ChatMessage::TYPE_CARD_MINI_TOURNAMENT,
                'content' => 'Gửi anh em thẻ kèo này nha!',
                'metadata' => ['target_id' => $mini->id],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.type', 'card_mini_tournament')
            ->assertJsonPath('data.card_data.id', $mini->id)
            ->assertJsonPath('data.card_data.title', 'Kèo Pickleball Tối Thứ 6')
            ->assertJsonPath('data.card_data.slots_text', '1/12 người tham gia')
            ->assertJsonPath('data.card_data.is_deleted', false);
    }

    public function test_send_and_resolve_player_card(): void
    {
        $player = User::factory()->create([
            'full_name' => 'Nguyễn Văn Pro',
            'self_score' => 4.25,
        ]);

        $res = $this->actingAs($this->userA, 'api')
            ->postJson("/api/chat/conversations/{$this->conversation->id}/messages", [
                'type' => ChatMessage::TYPE_CARD_PLAYER,
                'metadata' => ['target_id' => $player->id],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.type', 'card_player')
            ->assertJsonPath('data.card_data.id', $player->id)
            ->assertJsonPath('data.card_data.full_name', 'Nguyễn Văn Pro')
            ->assertJsonPath('data.card_data.rating', 4.25);
    }

    public function test_chat_shortcuts_endpoints(): void
    {
        // 1. Shortcuts matches
        $matchRes = $this->actingAs($this->userA, 'api')
            ->getJson('/api/chat/shortcuts/matches');
        $matchRes->assertStatus(200)->assertJsonPath('status', true);

        // 2. Shortcuts tournaments
        $tourRes = $this->actingAs($this->userA, 'api')
            ->getJson('/api/chat/shortcuts/tournaments');
        $tourRes->assertStatus(200)->assertJsonPath('status', true);

        // 3. Shortcuts players
        $playerRes = $this->actingAs($this->userA, 'api')
            ->getJson('/api/chat/shortcuts/players');
        $playerRes->assertStatus(200)->assertJsonPath('status', true);

        // 4. Shortcuts clubs
        $clubRes = $this->actingAs($this->userA, 'api')
            ->getJson('/api/chat/shortcuts/clubs');
        $clubRes->assertStatus(200)->assertJsonPath('status', true);
    }
}

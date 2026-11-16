<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatRecentSearch;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\Chat\UserBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChatRecentSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected User $authUser;
    protected User $userB;
    protected User $userC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authUser = User::factory()->create([
            'full_name' => 'Nguyễn Văn Chính',
            'email' => 'main_' . uniqid() . '@example.com',
            'visibility' => 'open',
        ]);

        $this->userB = User::factory()->create([
            'full_name' => 'Trần Thị Bình',
            'email' => 'binh_' . uniqid() . '@example.com',
            'visibility' => 'open',
        ]);

        $this->userC = User::factory()->create([
            'full_name' => 'Lê Minh Cường',
            'email' => 'cuong_' . uniqid() . '@example.com',
            'visibility' => 'open',
        ]);
    }

    public function test_record_and_list_recent_searches_ordered_by_latest(): void
    {
        // Tạo sẵn DM giữa authUser và userB để kiểm tra conversation_id
        $dm = Conversation::create(['type' => Conversation::TYPE_DM]);
        ConversationParticipant::create([
            'conversation_id' => $dm->id,
            'user_id' => $this->authUser->id,
            'role' => 'member',
        ]);
        ConversationParticipant::create([
            'conversation_id' => $dm->id,
            'user_id' => $this->userB->id,
            'role' => 'member',
        ]);

        Carbon::setTestNow(now()->subMinutes(10));
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $this->userB->id)
            ->assertJsonPath('data.conversation_id', $dm->id);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['user_id' => $this->userC->id])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->userC->id)
            ->assertJsonPath('data.conversation_id', null);

        Carbon::setTestNow();

        $res = $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches');

        $res->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->userC->id)
            ->assertJsonPath('data.1.id', $this->userB->id)
            ->assertJsonPath('data.1.conversation_id', $dm->id);
    }

    public function test_recording_existing_user_bumps_to_top_without_duplicate(): void
    {
        Carbon::setTestNow(now()->subMinutes(10));
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(200);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userC->id])
            ->assertStatus(200);

        // Tìm lại userB -> đẩy userB lên đầu, không tạo bản ghi trùng
        Carbon::setTestNow(now()->addMinutes(2));
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(200);

        Carbon::setTestNow();

        $this->assertEquals(
            2,
            ChatRecentSearch::where('user_id', $this->authUser->id)->count()
        );

        $res = $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches');

        $res->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->userB->id)
            ->assertJsonPath('data.1.id', $this->userC->id);
    }

    public function test_cannot_record_self_guest_banned_or_blocked_user(): void
    {
        // Self
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->authUser->id])
            ->assertStatus(400);

        // Guest
        $guest = User::factory()->create([
            'is_guest' => true,
            'email' => 'guest_' . uniqid() . '@example.com',
        ]);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $guest->id])
            ->assertStatus(400);

        // Banned
        $banned = User::factory()->create([
            'is_banned' => true,
            'email' => 'banned_' . uniqid() . '@example.com',
        ]);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $banned->id])
            ->assertStatus(400);

        // Blocked
        UserBlock::create([
            'user_id' => $this->authUser->id,
            'blocked_user_id' => $this->userB->id,
        ]);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(400);
    }

    public function test_recent_searches_excludes_blocked_and_private_users(): void
    {
        $privateUser = User::factory()->create([
            'full_name' => 'Private User',
            'email' => 'priv_' . uniqid() . '@example.com',
            'visibility' => 'open',
        ]);

        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(200);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userC->id])
            ->assertStatus(200);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $privateUser->id])
            ->assertStatus(200);

        // Sau đó userB bị chặn và privateUser chuyển sang private
        UserBlock::create([
            'user_id' => $this->userB->id,
            'blocked_user_id' => $this->authUser->id,
        ]);
        $privateUser->update(['visibility' => 'private']);

        $res = $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->userC->id);
    }

    public function test_remove_single_and_clear_all_recent_searches(): void
    {
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userB->id])
            ->assertStatus(200);
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userC->id])
            ->assertStatus(200);

        // Xóa 1 mục
        $this->actingAs($this->authUser, 'api')
            ->deleteJson('/api/chat/recent-searches/' . $this->userB->id)
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        // Xóa lại lần nữa -> 404
        $this->actingAs($this->authUser, 'api')
            ->deleteJson('/api/chat/recent-searches/' . $this->userB->id)
            ->assertStatus(404);

        // Còn 1 mục
        $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Xóa tất cả
        $this->actingAs($this->authUser, 'api')
            ->deleteJson('/api/chat/recent-searches')
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_count', 1);

        $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_max_recent_items_cap_prunes_oldest(): void
    {
        $users = User::factory()->count(22)->create(['visibility' => 'open']);
        $baseTime = now()->subMinutes(30);

        foreach ($users as $index => $u) {
            Carbon::setTestNow($baseTime->copy()->addMinutes($index));
            $this->actingAs($this->authUser, 'api')
                ->postJson('/api/chat/recent-searches', ['target_user_id' => $u->id])
                ->assertStatus(200);
        }

        Carbon::setTestNow();

        $this->assertEquals(
            ChatRecentSearch::MAX_RECENT_ITEMS,
            ChatRecentSearch::where('user_id', $this->authUser->id)->count()
        );

        $res = $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/recent-searches');

        $res->assertStatus(200)
            ->assertJsonCount(ChatRecentSearch::MAX_RECENT_ITEMS, 'data')
            ->assertJsonPath('data.0.id', $users->last()->id);
    }

    public function test_search_users_for_chat_with_keyword_and_empty_fallback(): void
    {
        $this->actingAs($this->authUser, 'api')
            ->postJson('/api/chat/recent-searches', ['target_user_id' => $this->userC->id])
            ->assertStatus(200);

        // Không nhập từ khóa -> trả về tìm kiếm gần đây
        $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/users/search')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->userC->id);

        // Tìm không dấu tiếng Việt
        $this->actingAs($this->authUser, 'api')
            ->getJson('/api/chat/users/search?q=' . urlencode('Thi Binh'))
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $this->userB->id]);
    }
}


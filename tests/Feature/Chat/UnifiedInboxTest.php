<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedInboxTest extends TestCase
{
    use DatabaseTransactions;

    protected User $userA;
    protected User $userB;
    protected User $strangerC;
    protected Conversation $convAcquaintance;
    protected Conversation $convStranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['full_name' => 'Nguyễn Văn A']);
        $this->userB = User::factory()->create(['full_name' => 'Trần Văn B (Bạn CLB)']);
        $this->strangerC = User::factory()->create(['full_name' => 'Lê Người Lạ C']);

        // Cho User A và B cùng CLB để là Acquaintance
        $club = Club::create([
            'name' => 'CLB Pickleball Ba Đình',
            'created_by' => $this->userA->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->userA->id,
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->userB->id,
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        // Tạo DM giữa A và B (Người quen)
        $resAB = $this->actingAs($this->userB, 'api')
            ->postJson('/api/chat/conversations/dm', ['receiver_id' => $this->userA->id]);
        $this->convAcquaintance = Conversation::find($resAB->json('data.id'));

        // B gửi tin nhắn cho A
        $this->actingAs($this->userB, 'api')
            ->postJson("/api/chat/conversations/{$this->convAcquaintance->id}/messages", [
                'type' => 'text',
                'content' => 'Hôm nay ra sân Ba Đình tập không?',
            ]);

        // Tạo DM giữa Stranger C và A (Người lạ)
        $resCA = $this->actingAs($this->strangerC, 'api')
            ->postJson('/api/chat/conversations/dm', ['receiver_id' => $this->userA->id]);
        $this->convStranger = Conversation::find($resCA->json('data.id'));

        // C gửi tin nhắn cho A
        $this->actingAs($this->strangerC, 'api')
            ->postJson("/api/chat/conversations/{$this->convStranger->id}/messages", [
                'type' => 'text',
                'content' => 'Chào bạn, làm quen giao lưu nhé!',
            ]);

        // Tạo 1 Thông báo hệ thống cho A
        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\SystemNotificationNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $this->userA->id,
            'data' => json_encode([
                'title' => 'Cập nhật hệ thống v2',
                'body' => 'Hệ thống đã nâng cấp tính năng chat realtime.',
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    public function test_inbox_tabs_and_strangers_isolation(): void
    {
        // 1. Tab Messages: chỉ có người quen (B), không có người lạ (C)
        $resMsg = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox?tab=messages');

        $resMsg->assertStatus(200)
            ->assertJsonPath('data.tab', 'messages')
            ->assertJsonPath('data.strangers_summary.total_conversations', 1)
            ->assertJsonPath('data.strangers_summary.unread_count', 1)
            ->assertJsonPath('data.strangers_summary.latest_message', 'Chào bạn, làm quen giao lưu nhé!');

        $items = collect($resMsg->json('data.items'));
        $this->assertTrue($items->contains('id', $this->convAcquaintance->id));
        $this->assertFalse($items->contains('id', $this->convStranger->id));

        // 2. Tab Alerts: chỉ có thông báo hệ thống
        $resAlerts = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox?tab=alerts');

        $resAlerts->assertStatus(200)
            ->assertJsonPath('data.tab', 'alerts');
        $alertItems = collect($resAlerts->json('data.items'));
        $this->assertCount(1, $alertItems);
        $this->assertEquals('Cập nhật hệ thống v2', $alertItems[0]['data']['title']);

        // 3. Tab All: hợp nhất cả hội thoại người quen và thông báo
        $resAll = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox?tab=all');

        $resAll->assertStatus(200)
            ->assertJsonPath('data.tab', 'all')
            ->assertJsonPath('data.strangers_summary.total_conversations', 1);

        $allItems = collect($resAll->json('data.items'));
        $this->assertCount(2, $allItems); // 1 conv + 1 alert
        $this->assertTrue($allItems->contains('item_type', 'conversation'));
        $this->assertTrue($allItems->contains('item_type', 'alert'));
    }

    public function test_strangers_queue_endpoint(): void
    {
        $res = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/strangers');

        $res->assertStatus(200);
        $items = collect($res->json('data.items'));
        $this->assertCount(1, $items);
        $this->assertEquals($this->convStranger->id, $items[0]['id']);
        $this->assertTrue($items[0]['is_stranger']);
    }

    public function test_unread_count_endpoint_separates_strangers(): void
    {
        $res = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/unread-count');

        $res->assertStatus(200)
            ->assertJsonPath('data.messages_unread', 1)
            ->assertJsonPath('data.alerts_unread', 1)
            ->assertJsonPath('data.strangers_unread', 1)
            // strangers_unread KHÔNG cộng dồn vào total_unread!
            ->assertJsonPath('data.total_unread', 2);
    }

    public function test_inbox_search(): void
    {
        // 1. Tìm kiếm theo tên người gửi tin nhắn
        $res1 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/search?q=' . urlencode('Trần'));

        $res1->assertStatus(200);
        $convs1 = collect($res1->json('data.conversations'));
        $this->assertTrue($convs1->contains('id', $this->convAcquaintance->id));

        // 2. Tìm kiếm theo nội dung tin nhắn
        $res2 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/search?q=' . urlencode('Ba Đình'));

        $res2->assertStatus(200);
        $convs2 = collect($res2->json('data.conversations'));
        $this->assertTrue($convs2->contains('id', $this->convAcquaintance->id));

        // 3. Tìm kiếm theo nội dung thông báo
        $res3 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/search?q=' . urlencode('nâng cấp'));

        $res3->assertStatus(200);
        $alerts3 = collect($res3->json('data.alerts'));
        $this->assertCount(1, $alerts3);
    }

    public function test_mark_all_as_read_by_tab(): void
    {
        // 1. Đánh dấu đã đọc tab messages
        $resMsg = $this->actingAs($this->userA, 'api')
            ->postJson('/api/inbox/read-all', ['tab' => 'messages']);
        $resMsg->assertStatus(200);

        // Kiểm tra messages_unread về 0, nhưng strangers và alerts vẫn còn
        $countRes1 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/unread-count');
        $countRes1->assertJsonPath('data.messages_unread', 0)
            ->assertJsonPath('data.alerts_unread', 1)
            ->assertJsonPath('data.strangers_unread', 1)
            ->assertJsonPath('data.total_unread', 1);

        // 2. Đánh dấu đã đọc tab strangers
        $resStr = $this->actingAs($this->userA, 'api')
            ->postJson('/api/inbox/read-all', ['tab' => 'strangers']);
        $resStr->assertStatus(200);

        $countRes2 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/unread-count');
        $countRes2->assertJsonPath('data.strangers_unread', 0);

        // 3. Đánh dấu đã đọc tab alerts
        $resAlert = $this->actingAs($this->userA, 'api')
            ->postJson('/api/inbox/read-all', ['tab' => 'alerts']);
        $resAlert->assertStatus(200);

        $countRes3 = $this->actingAs($this->userA, 'api')
            ->getJson('/api/inbox/unread-count');
        $countRes3->assertJsonPath('data.alerts_unread', 0)
            ->assertJsonPath('data.total_unread', 0);
    }
}

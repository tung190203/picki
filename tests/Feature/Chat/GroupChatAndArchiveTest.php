<?php

namespace Tests\Feature\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\User;
use App\Services\Chat\GroupChatSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GroupChatAndArchiveTest extends TestCase
{
    use DatabaseTransactions;

    protected GroupChatSyncService $syncService;
    protected User $user1;
    protected User $user2;
    protected User $virtualUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncService = app(GroupChatSyncService::class);

        $this->user1 = User::factory()->create(['full_name' => 'Real User 1', 'is_guest' => false]);
        $this->user2 = User::factory()->create(['full_name' => 'Real User 2', 'is_guest' => false]);
        $this->virtualUser = User::factory()->create(['full_name' => 'Virtual Member', 'is_guest' => true]);
    }

    public function test_sync_club_chat_excludes_virtual_members(): void
    {
        $club = Club::factory()->create(['name' => 'CLB Tennis & Pickleball']);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->user1->id,
            'role' => 'admin',
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->user2->id,
            'role' => 'member',
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        // Thành viên ảo trong CLB
        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $this->virtualUser->id,
            'role' => 'member',
            'membership_status' => 'joined',
            'status' => 'active',
        ]);

        $conversation = $this->syncService->syncClub($club);

        $this->assertEquals(Conversation::TYPE_CLUB, $conversation->type);
        $this->assertEquals('CLB ' . $club->name, $conversation->title);

        $participantUserIds = $conversation->participants()->pluck('user_id')->toArray();
        $this->assertContains($this->user1->id, $participantUserIds);
        $this->assertContains($this->user2->id, $participantUserIds);

        // Đảm bảo thành viên ảo không có trong nhóm chat
        $this->assertNotContains($this->virtualUser->id, $participantUserIds);
    }

    public function test_add_and_remove_user_from_group(): void
    {
        $club = Club::factory()->create(['name' => 'CLB Thể Thao']);

        // 1. Thêm user thực vào nhóm chat
        $participant = $this->syncService->addUserToGroup(Conversation::TYPE_CLUB, $club->id, $this->user1->id);
        $this->assertNotNull($participant);

        // 2. Thêm user ảo -> trả về null (bị từ chối)
        $virtualParticipant = $this->syncService->addUserToGroup(Conversation::TYPE_CLUB, $club->id, $this->virtualUser->id);
        $this->assertNull($virtualParticipant);

        // 3. Gỡ user thực khỏi nhóm chat
        $this->syncService->removeUserFromGroup(Conversation::TYPE_CLUB, $club->id, $this->user1->id);

        $exists = ConversationParticipant::where('user_id', $this->user1->id)
            ->whereHas('conversation', fn ($q) => $q->where('type', Conversation::TYPE_CLUB)->where('target_id', $club->id))
            ->exists();
        $this->assertFalse($exists);
    }

    public function test_auto_archive_inactive_chat_groups(): void
    {
        // 1. Kèo đã kết thúc và không hoạt động 35 ngày
        $miniClosed = MiniTournament::create([
            'name' => 'Kèo cũ đã đóng',
            'created_by' => $this->user1->id,
            'sport_id' => 1,
            'status' => MiniTournament::STATUS_CLOSED,
            'start_time' => now()->subDays(40),
            'end_time' => now()->subDays(35),
        ]);
        $convOld = Conversation::create([
            'type' => Conversation::TYPE_MINI_TOURNAMENT,
            'target_id' => $miniClosed->id,
            'title' => 'Kèo cũ đã đóng',
            'last_message_at' => now()->subDays(35),
            'is_archived' => false,
        ]);

        // 2. Kèo đang mở hoặc mới có tin nhắn 5 ngày trước
        $miniOpen = MiniTournament::create([
            'name' => 'Kèo đang hoạt động',
            'created_by' => $this->user1->id,
            'sport_id' => 1,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now(),
            'end_time' => now()->addDays(2),
        ]);
        $convActive = Conversation::create([
            'type' => Conversation::TYPE_MINI_TOURNAMENT,
            'target_id' => $miniOpen->id,
            'title' => 'Kèo đang hoạt động',
            'last_message_at' => now()->subDays(5),
            'is_archived' => false,
        ]);

        // Chạy command lưu trữ tự động
        $this->artisan('chat:auto-archive-inactive --days=30')
            ->assertSuccessful();

        $convOld->refresh();
        $convActive->refresh();

        $this->assertTrue($convOld->is_archived);
        $this->assertNotNull($convOld->archived_at);

        $this->assertFalse($convActive->is_archived);
        $this->assertNull($convActive->archived_at);
    }
}

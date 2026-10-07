<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Thay thế bảng `club_virtual_members` (user ảo cũ) bằng `club_guest_profiles`.
 *
 * Bảng mới dùng FK `user_id` thật vào `users` (User có `is_guest = true`), cho phép
 * guest này được tham chiếu như một user thật trong toàn bộ flow (participants,
 * staff, team_members, leaderboard) mà không cần cột snapshot riêng.
 *
 * Các bước migration:
 *  1. Tạo bảng `club_guest_profiles`.
 *  2. Convert từng `club_virtual_members` row → tạo/tìm `User.is_guest = true`
 *     (best-effort match theo name + phone NULL) rồi insert `club_guest_profiles`.
 *  3. Backfill `user_id` cho các bảng chứa snapshot `guest_*` dựa theo `guest_name`
 *     (participants, mini_participants, tournament_staff, mini_tournament_staff,
 *      team_members, mini_team_members, match_*).
 *  4. Drop bảng `club_virtual_members`.
 *  5. Drop cột `is_virtual`, `virtual_member_id` trên `tournament_staff` và
 *     `mini_tournament_staff` (giữ `guest_name`, `guest_avatar` để render lịch sử).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tạo bảng mới
        Schema::create('club_guest_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['club_id', 'user_id'], 'club_guest_profiles_club_user_unique');
            $table->index(['club_id', 'deleted_at']);
        });

        // 2. Convert từng ClubVirtualMember sang User.is_guest=true + club_guest_profiles
        $oldVms = DB::table('club_virtual_members')->get();
        $userCache = []; // [name => user_id] để tránh tạo trùng user trong cùng batch

        foreach ($oldVms as $vm) {
            $vmName = trim((string) $vm->name);
            if ($vmName === '') {
                continue;
            }

            // Best-effort match user theo tên (cache trước, fallback query DB)
            $userId = $userCache[$vmName] ?? null;
            if (!$userId) {
                $userId = DB::table('users')
                    ->where('is_guest', true)
                    ->whereNull('phone')
                    ->where('full_name', $vmName)
                    ->value('id');
            }

            if (!$userId) {
                $userId = DB::table('users')->insertGetId([
                    'full_name' => $vmName,
                    'phone' => null,
                    'avatar_url' => $vm->avatar_url,
                    'password' => Str::random(12),
                    'visibility' => 'private',
                    'is_guest' => true,
                    'last_active_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $userCache[$vmName] = $userId;

            // Insert club_guest_profiles (bỏ qua nếu đã tồn tại do duplicate VM)
            $exists = DB::table('club_guest_profiles')
                ->where('club_id', $vm->club_id)
                ->where('user_id', $userId)
                ->exists();
            if (!$exists) {
                DB::table('club_guest_profiles')->insert([
                    'club_id' => $vm->club_id,
                    'user_id' => $userId,
                    'notes' => $vm->notes,
                    'created_by' => $vm->created_by,
                    'created_at' => $vm->created_at ?? now(),
                    'updated_at' => $vm->updated_at ?? now(),
                ]);
            }
        }

        // 3. Backfill user_id cho các bảng chứa guest snapshot dựa theo guest_name
        // Match best-effort: User.is_guest = true + name khớp + phone NULL.
        $this->backfillGuestUserId('participants', 'tournament_id', 'Tournament');
        $this->backfillGuestUserId('mini_participants', 'mini_tournament_id', 'MiniTournament');
        $this->backfillGuestUserId('tournament_staff', 'tournament_id', 'Tournament');
        $this->backfillGuestUserId('mini_tournament_staff', 'mini_tournament_id', 'MiniTournament');
        $this->backfillGuestUserId('team_members', 'team_id', 'Team');
        $this->backfillGuestUserId('mini_team_members', 'mini_team_id', 'MiniTeam');

        // 4. Drop bảng cũ
        Schema::dropIfExists('club_virtual_members');
    }

    /**
     * Backfill user_id cho các record guest dựa theo guest_name. Chỉ update những
     * row có is_guest = true (nếu có cột) hoặc (user_id IS NULL AND guest_name IS NOT NULL).
     */
    private function backfillGuestUserId(string $table, string $fkCol, string $morph): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (!Schema::hasColumn($table, 'guest_name') || !Schema::hasColumn($table, 'user_id')) {
            return;
        }

        // Lấy danh sách (guest_name, user_id) từ User.is_guest = true (phone NULL)
        $rows = DB::table('users')
            ->where('is_guest', true)
            ->whereNull('phone')
            ->select('id', 'full_name')
            ->get()
            ->keyBy('full_name');

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $name => $user) {
            $name = (string) $name;
            if ($name === '') {
                continue;
            }
            DB::table($table)
                ->whereNull('user_id')
                ->where('guest_name', $name)
                ->update(['user_id' => $user->id]);
        }
    }

    public function down(): void
    {
        // Phục hồi cơ bản: tạo lại bảng club_virtual_members từ club_guest_profiles
        // (snapshot name/avatar từ users). Dữ liệu user_id của các bảng snapshot sẽ
        // KHÔNG rollback về NULL — đây là best-effort down.
        Schema::create('club_virtual_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->onDelete('cascade');
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['club_id', 'deleted_at']);
        });

        $profiles = DB::table('club_guest_profiles')
            ->join('users', 'users.id', '=', 'club_guest_profiles.user_id')
            ->select('club_guest_profiles.club_id', 'users.full_name as name', 'users.avatar_url', 'club_guest_profiles.notes', 'club_guest_profiles.created_by', 'club_guest_profiles.created_at', 'club_guest_profiles.updated_at')
            ->get();

        foreach ($profiles as $p) {
            DB::table('club_virtual_members')->insert([
                'club_id' => $p->club_id,
                'name' => $p->name,
                'avatar_url' => $p->avatar_url,
                'created_by' => $p->created_by,
                'notes' => $p->notes,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ]);
        }

        Schema::dropIfExists('club_guest_profiles');
    }
};

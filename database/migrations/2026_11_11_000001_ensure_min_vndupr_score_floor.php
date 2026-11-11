<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cập nhật điểm sàn tối thiểu 1.0 cho toàn bộ vndupr_score và khởi tạo cho user chưa có.
     */
    public function up(): void
    {
        // 1. Cập nhật tất cả điểm trình vndupr_score < 1.0 thành 1.0
        DB::table('user_sport_scores')
            ->where('score_type', 'vndupr_score')
            ->where('score_value', '<', 1.0)
            ->update([
                'score_value' => 1.0,
                'updated_at' => now(),
            ]);

        // 2. Tạo user_sport (Pickleball: sport_id = 1) cho các user chưa có
        $usersWithoutSport = DB::table('users')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('user_sport')
                    ->whereColumn('user_sport.user_id', 'users.id')
                    ->where('user_sport.sport_id', 1);
            })
            ->where('is_guest', false)
            ->whereNull('deleted_at')
            ->pluck('id');

        $now = now();
        $userSportInserts = [];
        foreach ($usersWithoutSport as $userId) {
            $userSportInserts[] = [
                'user_id' => $userId,
                'sport_id' => 1,
                'tier' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($userSportInserts, 500) as $chunk) {
            DB::table('user_sport')->insert($chunk);
        }

        // 3. Tạo user_sport_scores (vndupr_score = 1.0) cho các user_sport (sport_id = 1) chưa có vndupr_score
        $userSportsWithoutScore = DB::table('user_sport')
            ->where('sport_id', 1)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('user_sport_scores')
                    ->whereColumn('user_sport_scores.user_sport_id', 'user_sport.id')
                    ->where('user_sport_scores.score_type', 'vndupr_score');
            })
            ->pluck('id');

        $scoreInserts = [];
        foreach ($userSportsWithoutScore as $userSportId) {
            $scoreInserts[] = [
                'user_sport_id' => $userSportId,
                'score_type' => 'vndupr_score',
                'score_value' => 1.0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($scoreInserts, 500) as $chunk) {
            DB::table('user_sport_scores')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Điểm sàn 1.0 không cần rollback
    }
};


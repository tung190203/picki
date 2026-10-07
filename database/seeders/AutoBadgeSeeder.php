<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\BadgeType;
use Illuminate\Database\Seeder;

class AutoBadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'TOURNAMENT',
                'name' => 'Thành tích Giải đấu',
                'description' => 'Các huy hiệu đạt được khi tham gia và chiến thắng các giải đấu chính thức.'
            ],
            [
                'code' => 'MATCH',
                'name' => 'Thành tích Trận đấu & Kèo',
                'description' => 'Các huy hiệu đạt được khi đánh xếp hạng, tạo kèo hoặc tham gia kèo.'
            ],
            [
                'code' => 'RATING',
                'name' => 'Cột mốc Rating',
                'description' => 'Huy hiệu ghi nhận trình độ kỹ năng cá nhân qua hệ thống Rating.'
            ],
            [
                'code' => 'LEADERBOARD',
                'name' => 'Bảng xếp hạng',
                'description' => 'Dành cho những người chơi xuất sắc nhất trên Bảng xếp hạng Vpick hoặc Câu lạc bộ.'
            ],
            [
                'code' => 'EXPLORER',
                'name' => 'Khám phá & Trải nghiệm',
                'description' => 'Huy hiệu dành cho những người chơi năng động, tham gia thi đấu ở nhiều địa điểm khác nhau.'
            ],
            [
                'code' => 'PROFILE',
                'name' => 'Tài khoản',
                'description' => 'Huy hiệu ghi nhận hoạt động người dùng và sự gắn bó với cộng đồng.'
            ],
        ];

        foreach ($types as $typeData) {
            BadgeType::updateOrCreate(
                ['code' => $typeData['code']],
                [
                    'name' => $typeData['name'],
                    'description' => $typeData['description'],
                ]
            );
        }

        $badges = [
            // TOURNAMENT
            ['code' => 'CHAMPION_1', 'name' => 'Vô địch (1)', 'type' => 'TOURNAMENT'],
            ['code' => 'CHAMPION_5', 'name' => 'Vô địch (5)', 'type' => 'TOURNAMENT'],
            ['code' => 'CHAMPION_10', 'name' => 'Vô địch (10)', 'type' => 'TOURNAMENT'],
            ['code' => 'CHAMPION_30', 'name' => 'Vô địch (30)', 'type' => 'TOURNAMENT'],
            ['code' => 'FIRST_CHAMP', 'name' => 'Ngôi sao mới nhú', 'description' => 'Vô địch giải đấu đầu tiên tham gia', 'type' => 'TOURNAMENT'],
            ['code' => 'PERFECT_RUN', 'name' => 'Nhà vô địch tuyệt đối', 'description' => 'Thắng không rớt set nào', 'type' => 'TOURNAMENT'],
            ['code' => 'DARK_HORSE', 'name' => 'Ngựa ô', 'description' => 'Người/đội có điểm rating thấp nhất vô địch', 'type' => 'TOURNAMENT'],
            ['code' => 'TOUR_PARTICIPATION_10', 'name' => 'Khách quen Giải (10)', 'type' => 'TOURNAMENT'],
            ['code' => 'TOUR_PARTICIPATION_50', 'name' => 'Khách quen Giải (50)', 'type' => 'TOURNAMENT'],
            ['code' => 'GIANT_SLAYER_1', 'name' => 'Kẻ diệt khổng lồ (1)', 'description' => 'Thắng người/đội có rating cao hơn', 'type' => 'TOURNAMENT'],
            ['code' => 'GIANT_SLAYER_5', 'name' => 'Kẻ diệt khổng lồ (5)', 'type' => 'TOURNAMENT'],
            ['code' => 'GIANT_SLAYER_10', 'name' => 'Kẻ diệt khổng lồ (10)', 'type' => 'TOURNAMENT'],
            ['code' => 'GIANT_SLAYER_30', 'name' => 'Kẻ diệt khổng lồ (30)', 'type' => 'TOURNAMENT'],
            
            // MATCH
            ['code' => 'WIN_STREAK_5', 'name' => 'Chuỗi thắng 5 trận', 'type' => 'MATCH'],
            ['code' => 'WIN_STREAK_10', 'name' => 'Chuỗi thắng 10 trận', 'type' => 'MATCH'],
            ['code' => 'WIN_STREAK_20', 'name' => 'Chuỗi thắng 20 trận', 'type' => 'MATCH'],
            ['code' => 'MATCH_PARTICIPATION_10', 'name' => 'Cày thuê (10 trận)', 'type' => 'MATCH'],
            ['code' => 'MATCH_PARTICIPATION_50', 'name' => 'Cày thuê (50 trận)', 'type' => 'MATCH'],
            ['code' => 'MATCH_PARTICIPATION_200', 'name' => 'Cày thuê (200 trận)', 'type' => 'MATCH'],
            ['code' => 'MATCH_PARTICIPATION_500', 'name' => 'Cày thuê (500 trận)', 'type' => 'MATCH'],
            ['code' => 'GHOST_1', 'name' => 'Bóng ma (1)', 'description' => 'Đăng ký tham gia giải/kèo nhưng vắng mặt', 'type' => 'MATCH'],
            ['code' => 'GHOST_10', 'name' => 'Bóng ma (10)', 'type' => 'MATCH'],
            ['code' => 'GHOST_30', 'name' => 'Bóng ma (30)', 'type' => 'MATCH'],
            ['code' => 'GHOST_100', 'name' => 'Bóng ma (100)', 'type' => 'MATCH'],
            ['code' => 'HOST_1', 'name' => 'Chủ kèo (1)', 'description' => 'Tạo kèo thành công', 'type' => 'MATCH'],
            ['code' => 'HOST_10', 'name' => 'Chủ kèo (10)', 'type' => 'MATCH'],
            ['code' => 'HOST_50', 'name' => 'Chủ kèo (50)', 'type' => 'MATCH'],
            ['code' => 'HOST_200', 'name' => 'Chủ kèo (200)', 'type' => 'MATCH'],
            ['code' => 'ORGANIZER_1', 'name' => 'Nhà tổ chức (1)', 'description' => 'Tổ chức giải đấu', 'type' => 'MATCH'],
            ['code' => 'ORGANIZER_5', 'name' => 'Nhà tổ chức (5)', 'type' => 'MATCH'],
            ['code' => 'ORGANIZER_20', 'name' => 'Nhà tổ chức (20)', 'type' => 'MATCH'],
            ['code' => 'ORGANIZER_50', 'name' => 'Nhà tổ chức (50)', 'type' => 'MATCH'],
            ['code' => 'SNIPER_5', 'name' => 'Lính bắn tỉa (5)', 'description' => 'Xin vào slot cuối của giải/kèo', 'type' => 'MATCH'],
            ['code' => 'SNIPER_20', 'name' => 'Lính bắn tỉa (20)', 'type' => 'MATCH'],
            ['code' => 'SNIPER_50', 'name' => 'Lính bắn tỉa (50)', 'type' => 'MATCH'],
            ['code' => 'SNIPER_100', 'name' => 'Lính bắn tỉa (100)', 'type' => 'MATCH'],

            // RATING
            ['code' => 'RATING_3', 'name' => 'Rating 3.0+', 'type' => 'RATING'],
            ['code' => 'RATING_3_5', 'name' => 'Rating 3.5+', 'type' => 'RATING'],
            ['code' => 'RATING_4', 'name' => 'Rating 4.0+', 'type' => 'RATING'],
            ['code' => 'RATING_4_5', 'name' => 'Rating 4.5+', 'type' => 'RATING'],
            ['code' => 'RATING_5', 'name' => 'Rating 5.0+', 'type' => 'RATING'],

            // LEADERBOARD
            ['code' => 'TOP_BXH_100', 'name' => 'Top 100 BXH', 'type' => 'LEADERBOARD'],
            ['code' => 'TOP_BXH_50', 'name' => 'Top 50 BXH', 'type' => 'LEADERBOARD'],
            ['code' => 'TOP_BXH_10', 'name' => 'Top 10 BXH', 'type' => 'LEADERBOARD'],
            ['code' => 'TOP_BXH_3', 'name' => 'Top 3 BXH', 'type' => 'LEADERBOARD'],
            ['code' => 'TOP_BXH_1', 'name' => '#1 BXH', 'type' => 'LEADERBOARD'],

            // EXPLORER
            ['code' => 'EXPLORER_1', 'name' => 'Nhà thám hiểm (1)', 'description' => 'Chơi ở 1 sân', 'type' => 'EXPLORER'],
            ['code' => 'EXPLORER_5', 'name' => 'Nhà thám hiểm (5)', 'description' => 'Chơi ở 5 sân khác nhau', 'type' => 'EXPLORER'],
            ['code' => 'EXPLORER_20', 'name' => 'Nhà thám hiểm (20)', 'description' => 'Chơi ở 20 sân khác nhau', 'type' => 'EXPLORER'],
            ['code' => 'EXPLORER_50', 'name' => 'Nhà thám hiểm (50)', 'description' => 'Chơi ở 50 sân khác nhau', 'type' => 'EXPLORER'],
            ['code' => 'VN_EXPLORER_1', 'name' => 'VN Explorer (1 Tỉnh)', 'type' => 'EXPLORER'],
            ['code' => 'VN_EXPLORER_5', 'name' => 'VN Explorer (5 Tỉnh)', 'type' => 'EXPLORER'],
            ['code' => 'VN_EXPLORER_20', 'name' => 'VN Explorer (20 Tỉnh)', 'type' => 'EXPLORER'],
            ['code' => 'VN_EXPLORER_63', 'name' => 'VN Explorer (63 Tỉnh)', 'type' => 'EXPLORER'],
            ['code' => 'TOURING_1', 'name' => 'Touring Player (1 Club)', 'type' => 'EXPLORER'],
            ['code' => 'TOURING_5', 'name' => 'Touring Player (5 Club)', 'type' => 'EXPLORER'],
            ['code' => 'TOURING_20', 'name' => 'Touring Player (20 Club)', 'type' => 'EXPLORER'],
            ['code' => 'TOURING_50', 'name' => 'Touring Player (50 Club)', 'type' => 'EXPLORER'],

            // PROFILE
            ['code' => 'PROFILE_COMPLETED', 'name' => 'Hồ sơ hoàn chỉnh', 'description' => 'Hoàn thiện 100% thông tin cá nhân', 'type' => 'PROFILE'],
        ];

        foreach ($badges as $b) {
            Badge::updateOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'description' => $b['description'] ?? null,
                    'type' => $b['type'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Successfully seeded ' . count($badges) . ' badges and ' . count($types) . ' badge types.');
    }
}

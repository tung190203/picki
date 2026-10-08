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
        $types = array (
  0 => 
  array (
    'code' => 'contribute',
    'name' => 'Đóng góp',
    'description' => NULL,
  ),
  1 => 
  array (
    'code' => 'discover',
    'name' => 'Khám phá',
    'description' => NULL,
  ),
  2 => 
  array (
    'code' => 'competitive_record',
    'name' => 'Thành tích thi đấu',
    'description' => NULL,
  ),
  3 => 
  array (
    'code' => 'player_journey',
    'name' => 'Hành trình người chơi',
    'description' => NULL,
  ),
  4 => 
  array (
    'code' => 'scarce',
    'name' => 'Hiếm',
    'description' => NULL,
  ),
  5 => 
  array (
    'code' => 'milestone',
    'name' => 'Cột mốc hồ sơ',
    'description' => '',
  ),
);

        foreach ($types as $typeData) {
            BadgeType::updateOrCreate(
                ['code' => $typeData['code']],
                [
                    'name' => $typeData['name'],
                    'description' => $typeData['description'],
                ]
            );
        }

        $badges = array (
  0 => 
  array (
    'code' => 'VERIFIED',
    'name' => 'Đã Xác Minh',
    'type' => 'milestone',
    'description' => 'Huy hiệu này có khi tài khoản đã được xác minh',
  ),
  1 => 
  array (
    'code' => 'ANCHOR',
    'name' => 'Mỏ Neo',
    'type' => 'scarce',
    'description' => 'Huy hiệu có thể được cấp phát khi người chơi xác minh điểm trình bằng cách nộp bằng chứng SPCN/DUPR',
  ),
  2 => 
  array (
    'code' => 'CHAMPION',
    'name' => 'Quán Quân',
    'type' => 'scarce',
    'description' => 'Danh hiệu này đạt được khi người chơi vô địch giải',
  ),
  3 => 
  array (
    'code' => 'PICKI',
    'name' => 'Picki',
    'type' => 'scarce',
    'description' => 'Vật phẩm giới hạn. Cách duy nhất để có là... chờ vũ trụ gọi tên',
  ),
  4 => 
  array (
    'code' => 'TOUR_BRONZE',
    'name' => 'Bước Chân Khởi Đầu',
    'type' => 'player_journey',
    'description' => 'Huy hiệu được cấp phát khi tham gia tối thiểu 5 giải',
  ),
  5 => 
  array (
    'code' => 'FIRST_CHAMPION',
    'name' => 'First Champion',
    'type' => 'scarce',
    'description' => 'Nhận được huy hiệu này khi lần đầu vô địch giải',
  ),
  6 => 
  array (
    'code' => 'GIANT_SLAYER',
    'name' => 'Giant Slayer',
    'type' => 'scarce',
    'description' => 'Lần đầu người chơi thắng người có điểm trình cao hơn 0.5',
  ),
  7 => 
  array (
    'code' => 'SNIPER',
    'name' => 'Sniper',
    'type' => 'scarce',
    'description' => 'Người chơi nhận được huy hiệu này khi đạt 90% thắng trong tháng',
  ),
  8 => 
  array (
    'code' => 'GHOST',
    'name' => 'Ghost',
    'type' => 'scarce',
    'description' => 'Thắng 20 trận mà không để đối thủ quá 5 điểm',
  ),
  9 => 
  array (
    'code' => 'TOUR_SILVER',
    'name' => 'Tấm Chiếu Từng Trải',
    'type' => 'player_journey',
    'description' => 'Huy hiệu được cấp phát khi tham gia tối thiểu 10 giải',
  ),
  10 => 
  array (
    'code' => 'TOUR_GOLD',
    'name' => 'Dấu Ấn Rực Rỡ',
    'type' => 'player_journey',
    'description' => 'Huy hiệu được cấp phát khi tham gia tối thiểu 15 giải',
  ),
  11 => 
  array (
    'code' => 'TOUR_DIAMOND',
    'name' => 'Huyền Thoại Sân Đấu',
    'type' => 'player_journey',
    'description' => 'Huy hiệu được cấp phát khi tham gia tối thiểu 20 giải',
  ),
  12 => 
  array (
    'code' => 'MATCH_10',
    'name' => 'Tân Binh',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 10 trận',
  ),
  13 => 
  array (
    'code' => 'MATCH_100',
    'name' => 'Chiến Binh',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 10 trận',
  ),
  14 => 
  array (
    'code' => 'MATCH_300',
    'name' => 'Chuyên Gia',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 300 trận',
  ),
  15 => 
  array (
    'code' => 'MATCH_500',
    'name' => 'Cao Thủ',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 500 trận',
  ),
  16 => 
  array (
    'code' => 'MATCH_1000',
    'name' => 'Lão Làng',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 1000 trận',
  ),
  17 => 
  array (
    'code' => 'MATCH_3000',
    'name' => 'Huyền Thoại',
    'type' => 'player_journey',
    'description' => 'Huy hiệu này được cấp khi người chơi chơi được 3000 trận',
  ),
  18 => 
  array (
    'code' => 'STREAK_5',
    'name' => 'Nóng Máy',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi người chơi đạt chuỗi thắng 5',
  ),
  19 => 
  array (
    'code' => 'STREAK_10',
    'name' => 'Bùng Nổ',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi người chơi đạt chuỗi thắng 10',
  ),
  20 => 
  array (
    'code' => 'STREAK_15',
    'name' => 'Bất Bại',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi người chơi đạt chuỗi thắng 15',
  ),
  21 => 
  array (
    'code' => 'STREAK_25',
    'name' => 'Thăng Hạng',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi người chơi đạt chuỗi thắng 20',
  ),
  22 => 
  array (
    'code' => 'RATING_25',
    'name' => 'Tân Binh Nhập Cuộc',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 2.5',
  ),
  23 => 
  array (
    'code' => 'RATING_30',
    'name' => 'Người Chơi Phong Trào',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 3.0',
  ),
  24 => 
  array (
    'code' => 'RATING_35',
    'name' => 'Tay Vợt Trung Cấp',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 3.5',
  ),
  25 => 
  array (
    'code' => 'RATING_40',
    'name' => 'Chuyên Gia Đột Phá',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 4.0',
  ),
  26 => 
  array (
    'code' => 'RATING_45',
    'name' => 'Cao Thủ Thực Thụ',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 4.5',
  ),
  27 => 
  array (
    'code' => 'RATING_50',
    'name' => 'Đẳng Cấp Chuyên Nghiệp',
    'type' => 'competitive_record',
    'description' => 'Danh hiệu này đạt được khi trình độ người chơi đạt mốc 5.0',
  ),
  28 => 
  array (
    'code' => 'TOP_100',
    'name' => 'Top 100',
    'type' => 'competitive_record',
    'description' => 'Người chơi đạt được huy hiệu này khi vào top 100 trong BXH',
  ),
  29 => 
  array (
    'code' => 'TOP_50',
    'name' => 'Top 50',
    'type' => 'competitive_record',
    'description' => 'Người chơi đạt được huy hiệu này khi vào top 50 trong BXH',
  ),
  30 => 
  array (
    'code' => 'TOP_10',
    'name' => 'Top 10',
    'type' => 'competitive_record',
    'description' => 'Người chơi đạt được huy hiệu này khi vào top 10 trong BXH',
  ),
  31 => 
  array (
    'code' => 'TOP_3',
    'name' => 'TOP 3',
    'type' => 'competitive_record',
    'description' => 'Người chơi đạt được huy hiệu này khi vào top 3 trong BXH',
  ),
  32 => 
  array (
    'code' => 'TOP_1',
    'name' => 'Top 1',
    'type' => 'competitive_record',
    'description' => 'Người chơi đạt được huy hiệu này khi vào top 1 trong BXH',
  ),
  33 => 
  array (
    'code' => 'EXPLORER_10',
    'name' => 'Dấu Chân Đầu Tiên',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi trên 10 sân khác nhau',
  ),
  34 => 
  array (
    'code' => 'EXPLORER_30',
    'name' => 'Kẻ Lữ Hành',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi trên 30 sân khác nhau',
  ),
  35 => 
  array (
    'code' => 'EXPLORER_100',
    'name' => 'Bậc Thầy Chinh Phục',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi trên 100 sân khác nhau',
  ),
  36 => 
  array (
    'code' => 'EXPLORER_300',
    'name' => 'Huyền Thoại Viễn Chinh',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi trên 300 sân khác nhau',
  ),
  37 => 
  array (
    'code' => 'VN_EXPLORER_3',
    'name' => 'Tấm Chiếu Mới',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi ít nhất ở 3 tỉnh thành khác nhau',
  ),
  38 => 
  array (
    'code' => 'VN_EXPLORER_10',
    'name' => 'Tung Hoành Ngang Dọc',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi ít nhất ở 10 tỉnh thành khác nhau',
  ),
  39 => 
  array (
    'code' => 'VN_EXPLORER_40',
    'name' => 'Phá Đảo Việt Nam',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi chơi ít nhất ở 40 tỉnh thành khác nhau',
  ),
  40 => 
  array (
    'code' => 'TOURING_PLAYER_3',
    'name' => 'Bước Chân Du Đấu',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi tham gia đấu ít nhất tại 3 CLB khác nhau',
  ),
  41 => 
  array (
    'code' => 'TOURING_PLAYER_10',
    'name' => 'Lãng Khách Vô Danh',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi tham gia đấu ít nhất tại 10 CLB khác nhau',
  ),
  42 => 
  array (
    'code' => 'TOURING_PLAYER_30',
    'name' => 'Bóng Ma Sân Khách',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi tham gia đấu ít nhất tại 30 CLB khác nhau',
  ),
  43 => 
  array (
    'code' => 'TOURING_PLAYER_100',
    'name' => 'Huyền Thoại Du Đấu',
    'type' => 'discover',
    'description' => 'Người dùng nhận được huy hiệu này khi tham gia đấu ít nhất tại 100 CLB khác nhau',
  ),
);

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
# HƯỚNG DẪN THÊM MỚI HUY HIỆU TỰ ĐỘNG CẤP PHÁT (AUTO BADGE)

Hệ thống cấp phát huy hiệu hiện tại hoạt động dựa trên cơ chế **Lắng nghe Sự kiện (Event-Driven)**. 
Tài liệu này hướng dẫn bạn chi tiết từng bước để thêm một quy tắc tự động cấp phát huy hiệu mới mà **không làm xáo trộn code cũ**.

---

## BƯỚC 1: Khai báo dữ liệu Huy hiệu (Database)

Trước khi code logic cấp phát, huy hiệu phải tồn tại trong CSDL.
1. Vào trang Quản trị (Admin CMS) của hệ thống Picki.
2. Thêm mới một huy hiệu:
   - **Tên:** Ví dụ "Chuỗi 5 Thắng"
   - **Mã Code (QUAN TRỌNG):** Viết hoa không dấu, VD: `WINSTREAK_5`. Mã này sẽ dùng để gọi trong Code.
   - **Phân loại & Icon:** Tuỳ chọn theo mong muốn.

---

## BƯỚC 2: Kiểm tra Sự kiện (Event) đã có chưa?

Để hệ thống biết khi nào cần tính toán cấp huy hiệu, ta cần một chiếc "loa phát thanh" (Event).
- **Ví dụ:** Muốn cấp huy hiệu khi trận đấu kết thúc -> Tìm xem đã có event `MatchFinished` chưa.
- **Ví dụ:** Muốn cấp huy hiệu khi user tạo câu lạc bộ -> Tìm xem đã có event `ClubCreated` chưa.

> **Nếu chưa có Event:** Chạy lệnh tạo mới: `php artisan make:event TenSuKien`. Sau đó vào hàm/chỗ xảy ra hành động (vd: hàm tạo Club) và chèn dòng `event(new TenSuKien($bienDauVao));` vào cuối.
> **Nếu đã có Event:** Chuyển sang Bước 3.

---

## BƯỚC 3: Tạo File Quy tắc (Rule Class)

Thư mục chứa các quy tắc nằm ở: `app/Badges/Rules/`.

1. Tạo một file PHP mới, ví dụ `WinStreakBadgeRule.php`.
2. Cho class này implement (kế thừa) `BadgeRuleInterface`.
3. Điền logic vào 3 hàm bắt buộc:
   - `badgeCode()`: Trả về mã Code huy hiệu ở Bước 1.
   - `condition()`: Xác nhận sự kiện nào được phép chạy Rule này.
   - `handle()`: Viết logic kiểm tra (vd: lấy 5 trận gần nhất xem có thắng không) và gọi lệnh cấp phát.

**MẪU CODE CHO BƯỚC 3:**
```php
<?php
namespace App\Badges\Rules;

use App\Events\MatchFinished;
use App\Services\BadgeService;

class WinStreakBadgeRule implements BadgeRuleInterface
{
    // 1. Khai báo mã huy hiệu
    public function badgeCode(): string {
        return 'WINSTREAK_5';
    }

    // 2. Chặn cửa: Chỉ cho phép sự kiện MatchFinished đi qua
    public function condition($event): bool {
        return $event instanceof MatchFinished;
    }

    // 3. Logic xử lý chính
    public function handle($event): void {
        $userId = $event->match->winner_id; // (Ví dụ logic lấy user thắng trận)
        
        // (Viết logic kiểm tra chuỗi thắng ở đây...)
        $isWinStreak = true; // Giả sử là đạt 5 trận thắng
        
        if ($isWinStreak) {
            // Lệnh cấp phát! (GỌI ĐÚNG 1 DÒNG NÀY LÀ XONG)
            app(BadgeService::class)->awardBadge($userId, $this->badgeCode());
        }
    }
}
```

---

## BƯỚC 4: Đăng ký Rule vào Hệ thống (Subscriber)

Hệ thống sẽ không biết sự tồn tại của File Rule ở Bước 3 nếu bạn không đăng ký nó.

1. Mở file: `app/Listeners/BadgeAchievementSubscriber.php`.
2. Tìm mảng `$rules` ở ngay đầu file.
3. Map sự kiện (Bước 2) với Rule (Bước 3).

**Ví dụ:**
```php
    protected array $rules = [
        // Các rule cũ...
        \App\Events\TournamentCompleted::class => [
            \App\Badges\Rules\ChampionBadgeRule::class,
        ],
        
        // 👉 ĐĂNG KÝ MỚI Ở ĐÂY:
        \App\Events\MatchFinished::class => [
            \App\Badges\Rules\WinStreakBadgeRule::class,
        ],
    ];
```

🎉 **XONG! TỪ BÂY GIỜ HỆ THỐNG SẼ TỰ CHẠY MÀ KHÔNG CẦN BẠN CAN THIỆP GÌ THÊM.**

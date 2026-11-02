# Tài liệu API & Hệ thống — Tính năng Huy hiệu (Badge System)

Tài liệu này tổng hợp toàn bộ các API, cấu trúc cơ sở dữ liệu và quy tắc nghiệp vụ trong Hệ thống Huy hiệu (Badge System) mới được triển khai, thay thế cho hệ thống lưu trữ bằng text code cũ.

---

## 1. Cấu trúc Cơ sở dữ liệu (Database)

Hệ thống sử dụng 3 bảng chính:
- **`badge_types`**: Danh mục phân loại huy hiệu (`id`, `code`, `name`, `description`).
- **`badges`**: Chứa thông tin huy hiệu (`id`, `code`, `name`, `description`, `icon_url`, `type`, `priority`, `is_active`).
- **`user_badges`**: Bảng trung gian lưu lịch sử user nhận huy hiệu (`id`, `user_id`, `badge_id`, `is_featured`, `is_avatar`, `acquired_at`). *Đã chuyển từ cột `badge_code` sang dùng `badge_id`.*

---

## 2. Danh sách Endpoints API

| STT | Phương thức | Endpoint | Dành cho | Mô tả |
| --- | --- | --- | --- | --- |
| 1 | `GET` | `/api/admin/badge-types` | Admin | Lấy danh sách phân loại huy hiệu |
| 2 | `POST` | `/api/admin/badge-types` | Admin | Tạo mới phân loại huy hiệu |
| 3 | `PUT` | `/api/admin/badge-types/{id}` | Admin | Cập nhật phân loại huy hiệu |
| 4 | `GET` | `/api/admin/badges` | Admin | Lấy danh sách toàn bộ huy hiệu |
| 5 | `POST` | `/api/admin/badges` | Admin | Tạo mới huy hiệu (có hỗ trợ upload icon) |
| 6 | `PUT` | `/api/admin/badges/{badge}` | Admin | Cập nhật huy hiệu (có hỗ trợ thay đổi icon) |
| 7 | `GET` | `/api/user/{id}/badges` | User | Lấy danh sách huy hiệu của một User cụ thể (gồm `is_featured` và `is_avatar`) |
| 8 | `PUT` | `/api/user/badges/featured` | User | Cập nhật (Ghim) các huy hiệu hiển thị trong Profile cá nhân (không giới hạn số lượng) |
| 9 | `PUT` | `/api/user/badges/avatar` | User | Cập nhật (Khoe) huy hiệu lên avatar hiển thị BXH và các nơi khác (chọn tối đa 1 hoặc gỡ) |

---

## 3. Chi tiết các Endpoints

### 3.1. Lấy danh sách phân loại huy hiệu (Badge Types)
Lấy danh sách các loại huy hiệu có trong hệ thống (VD: Hạng, Sự kiện).

* **Endpoint:** `GET /api/admin/badge-types`
* **Headers:** `Authorization: Bearer {token}`

#### Response mẫu (`200 OK`):
```json
{
  "data": [
    {
      "id": 1,
      "code": "scarce",
      "name": "Hiếm",
      "description": "Các huy hiệu đạt được khi tham gia và chiến thắng các giải đấu chính thức.",
      "created_at": "2026-10-05T09:00:00.000000Z"
    }
  ]
}
```

**Yêu cầu thay đổi UI/UX trên App:**
- Ở trang Profile, phần hiển thị các bộ sưu tập huy hiệu, App cần **thêm một icon hình tròn dấu chấm hỏi (?)** bên cạnh Tiêu đề của loại huy hiệu (VD: `Hiếm (?)`).
- Khi user bấm/tap vào icon `(?)` này, App hiển thị một Tooltip hoặc Bottom Sheet nhỏ chứa nội dung của chuỗi `description` trả về từ API.

---

### 3.2. Tạo mới / Sửa Huy hiệu (Tích hợp Upload Icon)
Thêm mới một huy hiệu vào hệ thống.

* **Endpoint:** `POST /api/admin/badges` (Tạo) | `PUT /api/admin/badges/{badge}` (Sửa)
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`multipart/form-data`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `code` | string | **Có** | Mã code huy hiệu (Viết hoa, không dấu, vd: `VERIFIED`) |
| `name` | string | **Có** | Tên hiển thị (VD: Đã xác minh) |
| `type` | string | **Có** | Mã của Loại huy hiệu (VD: `scarce`) |
| `icon` | file | Không | File ảnh SVG/PNG tải lên |
| `is_active`| boolean | Không | Trạng thái hiển thị (1 hoặc 0) |

#### Response mẫu (`201 Created`):
```json
{
  "data": {
    "id": 1,
    "code": "VERIFIED",
    "name": "Đã xác minh",
    "icon_url": "/storage/badges/image.svg",
    "type": "scarce"
  }
}
```

---

### 3.3. Lấy danh sách huy hiệu của một User
Lấy toàn bộ huy hiệu trong hệ thống, kèm theo trạng thái người dùng này đã mở khoá hay chưa (`is_unlocked`), đã ghim trong profile (`is_featured`), và đã chọn khoe lên avatar (`is_avatar`).

* **Endpoint:** `GET /api/user/{id}/badges`
* **Headers:** `Authorization: Bearer {token}`

#### Response mẫu (`200 OK`):
```json
{
  "data": [
    {
      "id": 1,
      "user_badge_id": 92,
      "code": "VERIFIED",
      "name": "Đã xác minh",
      "icon_url": "/storage/badges/verified.svg",
      "type": "scarce",
      "type_name": "Hiếm",
      "is_unlocked": true,
      "is_featured": true,
      "is_avatar": true,
      "is_primary": true,
      "featured_order": 1730000000,
      "acquired_at": "2026-10-05T09:28:28.000000Z"
    },
    {
      "id": 2,
      "user_badge_id": null,
      "code": "CHAMPION",
      "name": "Quán quân",
      "is_unlocked": false,
      "is_featured": false,
      "is_avatar": false,
      "is_primary": false,
      "featured_order": null,
      "acquired_at": null
    }
  ]
}
```

---

### 3.4. Cập nhật huy hiệu ghim trong Profile cá nhân
Thiết lập danh sách các huy hiệu ghim hiển thị trong trang Profile cá nhân của người dùng đang đăng nhập (**không giới hạn số lượng ghim**). Có thể truyền thêm `avatar_user_badge_id` để cập nhật đồng thời huy hiệu avatar nếu muốn.

* **Endpoint:** `PUT /api/user/badges/featured`
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`application/json`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `user_badge_ids` | array | **Có** | Mảng chứa các `id` trong bảng `user_badges` cần ghim trong profile (VD: `[92, 95]`, truyền mảng rỗng `[]` nếu gỡ hết) |
| `avatar_user_badge_id` | integer \| null | Không | (Tuỳ chọn) ID của `user_badge` muốn ghim lên avatar trong cùng request |

#### Response mẫu (`200 OK`):
```json
{
  "message": "Featured badges updated successfully"
}
```

---

### 3.5. Cập nhật huy hiệu khoe lên avatar (Avatar Badge / Primary Badge)
Thiết lập đúng 1 huy hiệu duy nhất để khoe lên avatar (hiển thị cạnh avatar ở BXH và các nơi khác trong app). Nếu người dùng không chọn hoặc gỡ bỏ, hệ thống fallback về `null` (không hiển thị). Ngoài BXH cho phép hiển thị bất kỳ huy hiệu nào người dùng chọn khoe lên avatar.

* **Endpoint:** `PUT /api/user/badges/avatar` *(hoặc alias: `PUT /api/user/badges/primary`)*
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`application/json`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `user_badge_id` | integer \| null | Không | `id` trong bảng `user_badges` muốn khoe lên avatar. Truyền `null` nếu muốn gỡ bỏ hoàn toàn |

#### Response mẫu (`200 OK`):
```json
{
  "message": "Avatar badge updated successfully"
}
```
*(Khi truyền `user_badge_id: null`: `"message": "Avatar badge removed successfully"`)*

---

## 4. Script Đồng bộ Dữ liệu Cũ (Migration)

Để chuyển đổi đồng bộ từ dữ liệu lịch sử hệ thống cũ (dùng `badge_code` lưu bằng text) sang hệ thống khoá ngoại `badge_id` mới.

Chạy lệnh trên Tinker hoặc dùng một Command riêng biệt:

```bash
php artisan tinker
```

```php
// Đoạn script tự động map và cập nhật ID
$oldRecords = DB::table('user_badges')->whereNull('badge_id')->get();
foreach ($oldRecords as $record) {
    // Tìm badge theo mã code cũ đang lưu (ví dụ cột cũ tên là badge_code)
    $badge = \App\Models\Badge::where('code', $record->badge_code)->first();
    
    if ($badge) {
        DB::table('user_badges')
            ->where('id', $record->id)
            ->update(['badge_id' => $badge->id]);
    }
}
```

---

## 5. Cơ chế Trao Huy hiệu Tự động (Automated Badge Engine)

**Quan trọng:** App Mobile **KHÔNG CẦN** phải gọi thêm bất kỳ API nào để xin cấp/tính toán huy hiệu cho User. Toàn bộ logic đánh giá huy hiệu hiện đã được Backend tự động hoá hoàn toàn 100%.

Các huy hiệu được cấp tự động thông qua các hành vi tự nhiên của User trên App:
- **Ngay khi kết thúc 1 trận đấu (Match/Quick Match/Mini Match):** Backend tự động kiểm tra và trao các huy hiệu: *Chuỗi thắng (Win Streak), Cày thuê (Match Participation), Sát thủ (Giant Slayer), Bóng ma (Ghost), Nhà thám hiểm (Explorer - sân mới), Việt Nam Explorer (tỉnh mới).*
- **Ngay khi kết thúc 1 giải đấu (Tournament):** Backend tự động đánh giá và trao: *Vô địch, Ngựa ô, Huỷ diệt, Nhà tổ chức, v.v.*
- **Ngay khi tạo 1 kèo (Mini Tournament):** Backend đánh giá và trao huy hiệu *Chủ kèo (Host)*.
- **Mỗi đêm (Cronjob lúc 02:45 và 03:00):** Backend tự động cập nhật hệ thống và trao các huy hiệu liên quan đến *Cột mốc Rating (Rating Milestones)* và *Top Bảng xếp hạng (Leaderboard)*.

### 5.1. Update Profile (Huy hiệu "Hồ sơ hoàn chỉnh")

Huy hiệu **#62. Hồ sơ hoàn chỉnh** yêu cầu người dùng điền đầy đủ 100% thông tin.

**Trách nhiệm của App:**
- App cần tự đánh giá xem form profile của user (Avatar, Họ tên, SĐT, Giới tính, v.v.) đã được điền đủ 100% các trường quan trọng chưa.
- Nếu đủ 100%, khi gọi API cập nhật thông tin (`PUT /api/users/{id}` hoặc endpoint tương ứng), App **bắt buộc phải truyền thêm param:**
  ```json
  {
      // ...các trường thông tin khác
      "is_profile_completed": 1
  }
  ```
- **Backend xử lý:** Khi Backend nhận được `is_profile_completed = 1` (và trạng thái cũ là 0/false), Backend sẽ kích hoạt event `UserProfileCompleted` và trao huy hiệu ngay lập tức cho User.

### 5.2. Tính năng "Người kết nối" (Giới thiệu bạn bè - Referral)

- Huy hiệu **Người kết nối (Mời người dùng mới + đánh 1 trận)** hiện tại **đang được tạm gác lại**.
- Lý do: App chưa có tính năng Referral (Mã giới thiệu) nên Backend chưa có dữ liệu tracking `referrer_id`. App Team không cần xử lý huy hiệu này cho đến khi có Epic tính năng Referral.

---

## 6. Các lệnh Artisan (Cronjobs & Migration) dành cho System Admin

Để phục vụ việc đồng bộ huy hiệu hồi tố (retroactive) cho những User cũ, cũng như tính toán các huy hiệu yêu cầu lịch trình chạy ngầm hàng ngày, hệ thống đã bổ sung một số lệnh (Command). Quản trị viên cần nắm rõ các lệnh này:

### 6.1. Các lệnh Chạy Ngầm Tự Động (Cronjobs)
Các lệnh này đã được cấu hình chạy tự động trong `app/Console/Kernel.php`, tuy nhiên bạn cũng có thể gọi thủ công nếu cần thiết:

- `php artisan badges:evaluate-rating`
  - **Chức năng:** Cấp huy hiệu "Cột mốc Rating" dựa trên rating hiện tại của tất cả user.
  - **Lịch tự động:** Chạy lúc `02:45` sáng mỗi ngày.
- `php artisan badges:evaluate-leaderboard`
  - **Chức năng:** Cấp huy hiệu "Top BXH" dựa trên Bảng xếp hạng toàn hệ thống (Top 100, 50, 10, 3, 1).
  - **Lịch tự động:** Chạy lúc `03:00` sáng mỗi ngày.

### 6.2. Các lệnh Đồng Bộ Hồi Tố (Chỉ chạy thủ công 1 lần)
Các lệnh này dùng để cấp phát bù huy hiệu cho các dữ liệu/hoạt động diễn ra *trước khi hệ thống tự động* được đưa vào hoạt động (tránh thiệt thòi cho user cũ). **Chỉ cần chạy 1 lần duy nhất** ngay sau khi deploy lên Production và đã tạo đầy đủ Mã Huy Hiệu (Badge Code) trên Admin CMS:

- `php artisan badges:evaluate-profile`
  - **Chức năng:** Quét toàn bộ User đã có cờ `is_profile_completed = 1` trong database để trao bù huy hiệu **Hồ sơ hoàn chỉnh**.
- `php artisan badges:evaluate-sniper`
  - **Chức năng:** Đánh giá lại lịch sử tham gia của user để cấp bù huy hiệu **Lính bắn tỉa** (Tham gia kèo ít nhất X lần).
- `php artisan badges:seed-champion`
  - **Chức năng:** Quét lại toàn bộ các Giải đấu (Tournament) đã đóng (Closed) từ trước đến nay, và cấp bù huy hiệu **Vô địch**, **Ngựa ô**, **Huỷ diệt**, v.v. cho người chơi.
- `php artisan db:seed --class=BackfillCreatorBadgesSeeder`
  - **Chức năng:** Quét toàn bộ kèo đấu và giải đấu hợp lệ từ trước đến nay để cấp bù huy hiệu **Tạo Kèo** (`HOST_20`, `HOST_50`, `HOST_100`, `HOST_500`) và **Tạo Giải** (`ORGANIZER_1`, `ORGANIZER_5`, `ORGANIZER_20`, `ORGANIZER_50`) cho người tạo đạt mốc.

*(Lưu ý quan trọng: Trước khi chạy các lệnh hồi tố, hãy đảm bảo bạn đã vào Admin CMS tạo đầy đủ các Huy Hiệu với Mã (Code) tương ứng cho từng loại).*

---

## 7. Hướng dẫn Deploy lên Production (Deployment Checklist)

Khi đưa luồng code Huy hiệu mới này lên Server Production, bạn cần làm tuần tự theo các bước sau để đảm bảo hệ thống không bị lỗi và dữ liệu được đồng bộ chính xác:

### Bước 1: Kéo Code & Chạy Migration
Hệ thống có cập nhật thêm cột `description` cho bảng `badge_types` nên bắt buộc phải migrate database.
```bash
git pull origin <branch_name>
composer install
php artisan migrate
```

### Bước 2: Build Frontend (VueJS)
Do có sửa đổi ở giao diện Admin CMS và Modal ghim huy hiệu.
```bash
npm install
npm run build
```

### Bước 3: Clear Cache
Clear cache để hệ thống nhận diện các Event/Listener và Schedule (Cronjob) mới.
```bash
php artisan optimize:clear
```

### Bước 4: Chạy Seeder tạo dữ liệu Huy hiệu (BẮT BUỘC)
Trước khi chạy bất kỳ script đồng bộ nào, hệ thống **bắt buộc phải có sẵn các mã huy hiệu** trong DB. Thay vì phải lên CMS tạo bằng tay từng cái, bạn chỉ cần chạy lệnh sau để tự động sinh toàn bộ (hơn 50) huy hiệu và loại huy hiệu đã được cấu hình sẵn:
```bash
php artisan db:seed --class=AutoBadgeSeeder
```
*(Nếu huy hiệu nào chưa có thì sẽ tự tạo, có rồi thì sẽ bỏ qua hoặc update thông tin cơ bản. Sau khi chạy xong, bạn có thể vào CMS upload lại ảnh `icon` cho từng cái sau).*

### Bước 5: Chạy Script convert dữ liệu cũ (Dành cho User đã có huy hiệu)
Đồng bộ các huy hiệu cũ (lưu bằng text `badge_code`) sang hệ thống khoá ngoại `badge_id` mới.
```bash
php artisan tinker
```
*(Chạy đoạn code ở phần 4 của tài liệu này vào màn hình tinker)*.

### Bước 6: Chạy các Lệnh Đồng Bộ Hồi Tố (Retroactive)
Chạy các lệnh cấp bù huy hiệu cho các hành vi user đã thực hiện trước đây:
```bash
php artisan badges:seed-champion
php artisan badges:evaluate-sniper
php artisan badges:evaluate-profile
php artisan db:seed --class=BackfillCreatorBadgesSeeder
```

### Bước 7: Kiểm tra Cronjob
Đảm bảo VPS/Server của bạn đã cài đặt crontab trỏ vào lệnh `schedule:run` của Laravel (thường thì dự án đã có sẵn, chỉ cần check lại cho chắc):
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```
Done! Từ giờ trở đi mọi thứ sẽ vận hành hoàn toàn tự động! 🚀

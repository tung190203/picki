# Competition Locations - Locking / Banning Flow

Tài liệu mô tả luồng khoá (ban) **sân thi đấu** (`competition_locations`) của admin và cách hệ thống đảm bảo sân bị khoá **không thể được tìm kiếm, chọn và sử dụng** khi user tạo kèo/giải.

## 1. Cơ chế khoá

- Bảng `competition_locations` có cột `is_banned` (boolean, default `false`).
- Cột này được quản lý bởi admin thông qua endpoint:

  ```http
  POST /api/admin/competition-locations/{id}/ban
  Body: { "is_banned": true | false }
  ```

  File: [app/Http/Controllers/Admin/AdminCompetitionLocationManagementController.php](app/Http/Controllers/Admin/AdminCompetitionLocationManagementController.php)
  Service: [app/Services/Admin/AdminCompetitionLocationManagementService.php](app/Services/Admin/AdminCompetitionLocationManagementService.php) → `toggleBan()`

- Khi admin toggle `is_banned = true`, sân đó sẽ bị ẩn khỏi mọi luồng chọn sân của user thường. Admin vẫn thấy sân này trong trang quản lý để mở khoá.

## 2. Scope `active` trên model

Để chuẩn hoá filter "chỉ lấy sân đang hoạt động", thêm local scope:

```php
// app/Models/CompetitionLocation.php
public function scopeActive($query)
{
    return $query->where('is_banned', false);
}
```

Scope này được dùng ở **tất cả API user-facing** để lấy danh sách sân cho dropdown chọn.

## 3. Các API bị ảnh hưởng

### 3.1. Search/Filter lấy danh sách sân (dropdown chọn)

| API | File | Thay đổi |
|---|---|---|
| `POST /api/competition-locations/index` | [app/Http/Controllers/CompetitionLocationController.php](app/Http/Controllers/CompetitionLocationController.php) | Thêm `->active()` ngay sau `withFullRelations()` |
| `GET /api/search/v2` (tab `court`) | [app/Http/Controllers/SearchV2Controller.php](app/Http/Controllers/SearchV2Controller.php) | Thêm `->active()` cho `TAB_COURT` |
| `GET /api/admin/competition-locations` | [app/Http/Controllers/Admin/AdminCompetitionLocationManagementController.php](app/Http/Controllers/Admin/AdminCompetitionLocationManagementController.php) | **KHÔNG áp dụng** — admin cần thấy cả sân bị khoá |

### 3.2. Endpoint tạo/sửa (submit) — chặn hard nếu sân đã bị khoá

Mọi endpoint submit nhận `competition_location_id` đều có 2 lớp bảo vệ:

1. **FormRequest rule**: `Rule::exists('competition_locations', 'id')->where(fn($q) => $q->where('is_banned', false))` — chặn tại validate (422 "Địa điểm thi đấu không hợp lệ").
2. **Controller check tường minh**: nếu location tồn tại và `is_banned = true` → trả `422 - "Địa điểm tạm thời bị cấm truy cập"`.

| Luồng | File | Đã có sẵn? |
|---|---|---|
| Tạo/Sửa Mini Tournament (kèo) | [app/Http/Controllers/MiniTournamentController.php](app/Http/Controllers/MiniTournamentController.php) | Đã có từ trước (store + update) |
| Tạo/Sửa Tournament (giải) | [app/Http/Controllers/TournamentController.php](app/Http/Controllers/TournamentController.php) | Mới thêm |
| Tạo Quick Match (trận nhanh) | [app/Http/Controllers/QuickMatchController.php](app/Http/Controllers/QuickMatchController.php) | Mới thêm |
| Tạo/Sửa Club Mini Tournament | [app/Http/Controllers/Club/ClubMiniTournamentController.php](app/Http/Controllers/Club/ClubMiniTournamentController.php) | Mới thêm |

### 3.3. FormRequest validation

| Request | File | Rule mới |
|---|---|---|
| `StoreTournamentRequest` | [app/Http/Requests/StoreTournamentRequest.php](app/Http/Requests/StoreTournamentRequest.php) | `Rule::exists` với `where is_banned=false` |
| `UpdateTournamentRequest` | [app/Http/Requests/UpdateTournamentRequest.php](app/Http/Requests/UpdateTournamentRequest.php) | `Rule::exists` với `where is_banned=false` |
| `StoreMiniTournamentRequest` | [app/Http/Requests/StoreMiniTournamentRequest.php](app/Http/Requests/StoreMiniTournamentRequest.php) | `Rule::exists` với `where is_banned=false` |
| `UpdateMiniTournamentRequest` | [app/Http/Requests/UpdateMiniTournamentRequest.php](app/Http/Requests/UpdateMiniTournamentRequest.php) | `Rule::exists` với `where is_banned=false` |

## 4. Hành vi phản hồi lỗi

| Trường hợp | HTTP Status | Message |
|---|---|---|
| Submit location_id không tồn tại | 422 | "Địa điểm thi đấu không hợp lệ" |
| Submit location_id đang bị khoá (`is_banned=true`) | 422 | "Địa điểm tạm thời bị cấm truy cập" |

## 5. Frontend

Không cần thay đổi frontend. Dropdown Vue ở `CreateTournamentPage.vue` và `CreateMiniTournamentPage.vue` sẽ tự động không thấy sân bị khoá vì backend trả về danh sách đã lọc qua `scopeActive`.

## 6. Lưu ý vận hành

- Khi admin khoá một sân, các **kèo/giải đã tạo trước đó với sân đó vẫn hoạt động bình thường** (không cascade). Chỉ chặn **tạo mới** và **chỉnh sửa** đổi sang sân khác hoặc set lại sân cũ.
- Sân bị khoá **vẫn hiển thị trên các trang chi tiết kèo/giải đang dùng nó** (chỉ là chặn dùng tiếp).
- Nếu user có ID sân bị khoá trong cache frontend (form nháp), khi submit sẽ nhận `422`.

## 7. Test thủ công

1. Vào admin → tab "Sân thi đấu" → bấm "Khoá" một sân bất kỳ.
2. Mở app/mobile → vào form tạo kèo → search sân vừa khoá → không xuất hiện trong dropdown.
3. Thử submit API trực tiếp với `competition_location_id` của sân bị khoá:
   - `POST /api/mini-tournaments` → 422 "Địa điểm tạm thời bị cấm truy cập"
   - `POST /api/tournaments` → 422
   - `POST /api/quick-matches` → 422
   - `POST /api/clubs/{id}/mini-tournaments` → 422
4. Mở khoá sân (unban) → sân xuất hiện lại trong dropdown và submit được.

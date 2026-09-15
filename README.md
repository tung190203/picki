# -----------------------------------------------
# 🚀 SETUP DỰ ÁN LARAVEL SAU KHI CLONE
# -----------------------------------------------

# Bước 1: Cài đặt thư viện PHP qua Composer
composer install

# Bước 2: Tạo file .env từ .env.example
cp .env.example .env

# Bước 3: Tạo khóa ứng dụng Laravel
php artisan key:generate

# Bước 4: (Thực hiện thủ công)
echo "➡️ Hãy mở file .env và cấu hình kết nối cơ sở dữ liệu (DB_DATABASE, DB_USERNAME, DB_PASSWORD...)"

# Bước 5: Tạo bảng trong cơ sở dữ liệu
php artisan migrate

# Bước 6: Build frontend (nếu có dùng Vite hoặc Mix)
npm install
npm run dev-all

# Bước 7: Truy cập ứng dụng
echo "✅ Truy cập ứng dụng tại: http://localhost:8000"

# Bước 8: (Tuỳ chọn) Tạo lại tài liệu API nếu có thay đổi
php artisan scribe:generate
echo "📘 Tài liệu API có tại: http://localhost:8000/docs"

# Import tỉnh thành phố bằng lệnh command 
php artisan import:provinces 2025-08-20
có thể bỏ phần optional ngày tháng năm 
php artisan import:provinces
# -----------------------------------------------
Thay đổi theo ngày tháng năm hiện tại để lấy dữ liệu mới nhất { yyyy-mm-dd }

# -----------------------------------------------
seeders data
php artisan db:seed 

# -----------------------------------------------
php artisan app:import-location-into-competition-location

# -----------------------------------------------
build code for production/staging
commit code->run this command:
./deploy-local.sh

# -----------------------------------------------
# 📋 GHI CHÚ KỸ THUẬT
# -----------------------------------------------

## Sơ đồ thi đấu (Bracket) - Format Mixed

### Vấn đề đã fix
Modal "Xem chi tiết BXH" trong ScheduleTab.vue hiển thị sai bracket cho format Mixed (format=1).

### Nguyên nhân
- Backend API `api/tournament-types/{id}/bracket` trả về cấu trúc `knockout_stage` (data gốc) và `leftSide`/`rightSide` (đã chia sẵn theo logic `next_position`)
- Logic backend chia left/right dựa trên `next_match_id` và `next_position` không chính xác về mặt bố cục bracket
- Frontend ban đầu dùng `leftSide`/`rightSide` từ backend, dẫn đến hiển thị sai

### Giải pháp
1. **ScheduleTab.vue** (`getMatches`): Ưu tiên dùng `knockout_stage` từ API, tự chia left/right theo logic:
   - Mỗi round: một nửa đầu matches → leftSide, một nửa sau → rightSide
   - Round cuối (final): tách final match và third place match ra riêng

2. **BracketMixedPreview.vue**:
   - Thêm helper `computeLeftRightFromKnockoutStage()` để tự tính lại left/right từ `knockout_stage`
   - Cập nhật `leftRounds`, `rightRounds`, `finalMatch`, `thirdPlaceMatch` computed để fallback về `knockout_stage` khi leftSide/rightSide rỗng
   - Đảm bảo `knockout_stage` được giữ lại trong `bracket.value` khi fetch data

### API Endpoint
- `GET /api/tournament-types/{id}/bracket` - Trả về bracket data (format Mixed)
- Response chứa: `pool_stage`, `knockout_stage`, `leftSide`, `rightSide`, `finalMatch`, `thirdPlaceMatch`

### Cấu trúc knockout_stage
```json
{
  "knockout_stage": [
    {
      "round": 2,
      "round_name": "Tứ kết",
      "matches": [...]
    },
    {
      "round": 3,
      "round_name": "Bán kết",
      "matches": [...]
    },
    {
      "round": 4,
      "round_name": "Chung kết",
      "matches": [
        { "match_id": 1, "is_third_place": false, ... },
        { "match_id": 2, "is_third_place": true, ... }
      ]
    }
  ]
}
```

---

## Background cho modal BracketMixedPreview

### Mô tả
Cho phép BTC (organizer/staff/club-staff) **tùy chỉnh ảnh nền** cho modal sơ đồ thi đấu (`BracketMixedPreview`).
- Nếu **chưa có ảnh** → dùng ảnh mặc định `@/assets/images/bracket-bg.png`.
- Nếu **đã upload** → dùng ảnh đã lưu trong DB.
- Có thể **xoá ảnh** để quay về mặc định.

### Backend

#### Migration
File: `database/migrations/2026_09_15_130000_add_bracket_background_to_tournaments_table.php`

Thêm cột `bracket_background` (varchar 255, nullable) vào bảng `tournaments`.

#### Model
File: `app/Models/Tournament.php`
- Thêm `bracket_background` vào `$fillable`
- Thêm accessor `getBracketBackgroundUrlAttribute()` (tự động thêm prefix `asset('storage/...')`)
- Append `bracket_background_url` vào `$appends`

#### Controller
File: `app/Http/Controllers/TournamentController.php`

| Method | URL | Quyền | Mô tả |
|---|---|---|---|
| `GET` | `/api/tournaments/{id}/bracket-background` | Auth | Lấy URL ảnh background hiện tại |
| `POST` | `/api/tournaments/{id}/bracket-background` | Auth + organizer/staff | Upload ảnh mới (multipart) hoặc `remove_background=1` để xoá |

**Request (upload):**
- `bracket_background`: file ảnh (jpg/jpeg/png/webp, max 5MB)

**Request (xoá):**
- `remove_background`: `1`

**Response:**
```json
{
  "tournament_id": 269,
  "bracket_background_url": "https://example.com/storage/tournaments/bracket-backgrounds/bracket_bg_xxx.webp"
}
```

Ảnh được lưu vào `storage/app/public/tournaments/bracket-backgrounds/`, được resize về max width 1920px, quality 80, convert sang webp.

#### Routes
File: `routes/api.php`
```php
Route::prefix('tournaments')->group(function () {
    // ... existing routes
    Route::get('/{id}/bracket-background', [TournamentController::class, 'getBracketBackground']);
    Route::post('/{id}/bracket-background', [TournamentController::class, 'updateBracketBackground']);
});
```

### Frontend

#### Service
File: `resources/js/service/tournament.js`

Thêm 3 functions:
- `getBracketBackground(tournamentId)` - Lấy URL ảnh
- `updateBracketBackground(tournamentId, file)` - Upload ảnh mới
- `removeBracketBackground(tournamentId)` - Xoá ảnh (về mặc định)

#### Component `BracketMixedPreview.vue`
Props mới:
- `isCreator: Boolean` - Có phải creator không (để hiện nút đổi background)
- `bracketBackgroundUrl: String|null` - URL ảnh background

Tính năng:
- Nút **"Đổi ảnh nền"** ở góc phải trên (chỉ hiện khi `isCreator=true`)
- Modal upload với preview, validate file (size, type), progress indicator
- Nút **"Xoá ảnh (về mặc định)"** nếu đang có ảnh custom
- Background áp dụng qua inline style (`bracketContainerStyle` computed)
- Fallback về CSS class `.bracket-bg-container` (ảnh mặc định) khi `bracketBackgroundUrl=null`

#### Component `ScheduleTab.vue`
Truyền thêm 2 props cho `BracketMixedPreview`:
```vue
<BracketMixedPreview
    :tournamentId="data?.id"
    :bracketData="mixedBracket"
    :rankData="rank"
    :isCreator="isCreator"
    :bracketBackgroundUrl="data?.bracket_background_url || null"
    @close="showRankingModal = false"
/>
```

#### TournamentResource
File: `app/Http/Resources/TournamentResource.php`
Thêm `bracket_background_url` vào response để frontend có thể lấy được URL qua API `GET /api/tournaments/{id}`.

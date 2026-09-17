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

---

## Lưu và khôi phục Leaderboard Scope

### Mô tả
Khi user chọn scope (tab) trong bảng xếp hạng (all/allClubs/club/friend), hệ thống sẽ tự động lưu lựa chọn này. Khi user load lại trang, scope được khôi phục về lựa chọn cuối cùng.

### Backend

#### AuthController.php
File: `app/Http/Controllers/AuthController.php`

API `/me` trả về `settings.leaderboard_scope` trong user data.

#### LeaderboardController.php
File: `app/Http/Controllers/LeaderboardController.php`

Method `getLeaderboard()` (line 358-429):
- Tự động lưu scope vào `user->settings['leaderboard_scope']` mỗi khi gọi API (line 370-379)
- Scope được lưu: `all`, `allClubs`, `club`, `friend`

### Frontend

#### LeaderboardSection.vue
File: `resources/js/components/pages/dashboard/LeaderboardSection.vue`

**Thay đổi:**
1. Thêm `tabFromScope` map để chuyển đổi scope → tab:
```javascript
const tabFromScope = {
  all: "all",
  allClubs: "allClubs",
  club: "clubMembers",
  friend: "friend",
};
```

2. Sử dụng `watch()` để khôi phục scope khi user data load:
```javascript
// Watch user data để khôi phục scope khi user data đã load
watch(() => getUser.value?.settings?.leaderboard_scope, (savedScope) => {
  if (savedScope && tabFromScope[savedScope] && activeTab.value === "all") {
    // Chỉ khôi phục nếu vẫn đang ở tab mặc định (chưa user thay đổi)
    activeTab.value = tabFromScope[savedScope];
  }
}, { immediate: true });
```

#### LeaderboardPage.vue (mới)
File: `resources/js/components/pages/leader-board/LeaderboardPage.vue`

Component mới thay thế `Leaderboard.vue` (fake data) bằng API thật:
- Tương tự `LeaderboardSection.vue` nhưng full-page với table layout
- Hỗ trợ 4 tabs: Top 50 Việt Nam, BXH CLB, Thành viên CLB, BXH Bạn bè
- Hiển thị avatar, badges, clubs, VNDUPR score, weekly change
- Pagination đầy đủ
- Tự động khôi phục scope từ user settings khi mount

#### Router
File: `resources/js/router/router.js`

Cập nhật route `/leaderboard` để dùng `LeaderboardPage.vue` thay vì `Leaderboard.vue`

### Flow hoạt động
1. **Lần đầu**: User chọn tab → API lưu scope vào database
2. **Load lại**: 
   - Frontend watch `user.settings.leaderboard_scope` (reactive)
   - Khi user data load xong từ `/me`, watcher tự động khôi phục tab đã chọn
   - Chỉ khôi phục nếu user chưa thay đổi tab (vẫn ở tab mặc định "all")
3. **Tự động**: Mỗi lần gọi API, backend tự động update scope mới

### Lưu ý kỹ thuật
- **QUAN TRỌNG**: Các component sử dụng leaderboard PHẢI gọi `await userStore.fetchMe()` trong `onMounted()` trước khi load data
- User store load từ localStorage khi khởi tạo, nhưng `settings` mới nhất cần được fetch từ server qua `/me`
- Sử dụng `watch(() => getUser.value?.settings?.leaderboard_scope)` với `immediate: true` để khôi phục scope
- Điều này đảm bảo khôi phục scope ngay khi user data từ `/me` load xong, không bị race condition
- Watcher chỉ trigger khi user data thay đổi, tránh conflict với user manually chọn tab

### Components đã cập nhật
1. **DashboardPage.vue**: Thêm `await userStore.fetchMe()` trong `onMounted()`
2. **LeaderboardSection.vue**: Thêm `await userStore.fetchMe()` trong `onMounted()`
3. **LeaderboardPage.vue**: Thêm `await userStore.fetchMe()` trong `onMounted()`

### API Endpoint
- `GET /api/leaderboard?scope={all|allClubs|club|friend}&club_id={id}&page={page}&per_page={limit}`
- Backend tự động save scope vào user settings mỗi lần gọi

---

## Fix: Validation thể thức kèo đấu (Match Format) Mini Tournament

### Vấn đề
Khi cập nhật `match_format` (rank_pairing, mixed_gender) qua API `PUT /api/clubs/{id}/mini-tournaments/{id}`, hệ thống validate **quá sớm** - ngay khi chọn thể thức, mặc dù user chưa phân nhóm (A/B hoặc Nam/Nữ).

**Lỗi trả về:**
```json
{
  "success": false,
  "message": "rank_pairing đánh đôi cần ít nhất 2 người mỗi nhóm, hiện có 0 nhóm A và 0 nhóm B.",
  "errors": {
    "match_format": [
      "rank_pairing đánh đôi cần ít nhất 2 người mỗi nhóm, hiện có 0 nhóm A và 0 nhóm B.",
      "Không thể thay đổi thể thức kèo đấu khi đã bắt đầu nhập điểm hoặc đã có trận đấu."
    ]
  }
}
```

### Nguyên nhân
File: `app/Http/Requests/UpdateMiniTournamentRequest.php` (dòng 270-304)

Validation logic trong `withValidator()` kiểm tra số lượng người chơi đã phân nhóm **ngay khi update match_format**, nhưng luồng thực tế là:
1. User chọn `match_format` (rank_pairing/mixed_gender) → **Chưa có dữ liệu phân nhóm**
2. User vào màn hình trung gian phân nhóm (A/B hoặc Nam/Nữ) → **Mới submit dữ liệu phân nhóm**
3. System bắt đầu session và generate matches → **Validate phân nhóm**

### Giải pháp
**Xóa validation phân nhóm khỏi `UpdateMiniTournamentRequest`**, vì:
- Validation này đã được triển khai đúng chỗ trong `MiniTournamentController::startSession()` (dòng 1497-1555)
- `startSession()` là API nhận dữ liệu phân nhóm (`participant_ids`) và validate đầy đủ trước khi generate matches

### Code Changes

#### File: `app/Http/Requests/UpdateMiniTournamentRequest.php`
**Trước đây (dòng 269-304):**
```php
// partner_rotation requires 3 to 8 confirmed participants
// mixed_gender requires at least 1 male and 1 female (or 2 each for double)
// rank_pairing requires at least 1 in group A and 1 in group B (or 2 each for double)
$newMatchFormat = $this->input('match_format');
if ($newMatchFormat !== null && $miniTournamentId) {
    $miniTournament = \App\Models\MiniTournament::find($miniTournamentId);
    if ($miniTournament) {
        $participants = $miniTournament->participants()->where('is_confirmed', true);
        if ($newMatchFormat === MiniTournament::MATCH_FORMAT_PARTNER_ROTATION) {
            // ... validation code
        } elseif ($newMatchFormat === MiniTournament::MATCH_FORMAT_MIXED_GENDER) {
            // ... validation code
        } elseif ($newMatchFormat === MiniTournament::MATCH_FORMAT_RANK_PAIRING) {
            // ... validation code
        }
    }
}
```

**Sau khi sửa (dòng 269-273):**
```php
// Validation for match_format participant requirements is handled in startSession API
// where grouping data is submitted. We don't validate here because:
// 1. User first selects match_format (no grouping data yet)
// 2. User then submits grouping (male/female or a/b) in an intermediate screen
// 3. Validation happens when starting the session with grouping data
```

### Validation hiện tại (đã có sẵn, không thay đổi)

#### File: `app/Http/Controllers/MiniTournamentController.php`
Method: `startSession()` (dòng 1532-1557)

**partner_rotation:**
```php
$count = count($participantIds);
if ($count < 3 || $count > 8) {
    return ResponseHelper::error('partner_rotation cần 3 đến 8 người đã xác nhận, đang có ' . $count . ' người', 422);
}
```

**mixed_gender:**
```php
$maleIds = $confirmedParticipants->where('player_group', 'male')->pluck('id')->toArray();
$femaleIds = $confirmedParticipants->where('player_group', 'female')->pluck('id')->toArray();
if (count($maleIds) < 1 || count($femaleIds) < 1) {
    return ResponseHelper::error('mixed_gender cần ít nhất 1 nam và 1 nữ đã phân nhóm', 422);
}
if ($isDouble && (count($maleIds) < 2 || count($femaleIds) < 2)) {
    return ResponseHelper::error('double mixed_gender cần ít nhất 2 nam và 2 nữ đã phân nhóm', 422);
}
```

**rank_pairing:**
```php
$aIds = $confirmedParticipants->where('player_group', 'a')->pluck('id')->toArray();
$bIds = $confirmedParticipants->where('player_group', 'b')->pluck('id')->toArray();
if (count($aIds) < 1 || count($bIds) < 1) {
    return ResponseHelper::error('rank_pairing cần ít nhất 1 người nhóm A và 1 người nhóm B đã phân nhóm', 422);
}
if ($isDouble && (count($aIds) < 2 || count($bIds) < 2)) {
    return ResponseHelper::error('double rank_pairing cần ít nhất 2 người mỗi nhóm', 422);
}
```

### Luồng hoạt động sau khi fix
1. **Chọn thể thức** → `PUT /api/clubs/{id}/mini-tournaments/{id}` với `match_format=rank_pairing` → ✅ **Không validate phân nhóm**
2. **Phân nhóm** → User vào màn hình trung gian, chọn A/B hoặc Nam/Nữ
3. **Bắt đầu session** → `POST /api/mini-tournaments/{id}/start-session` với `participant_ids={...}` → ✅ **Validate phân nhóm đầy đủ**
4. **Generate matches** → Tạo lịch thi đấu dựa trên phân nhóm

### API liên quan
- `PUT /api/clubs/{clubId}/mini-tournaments/{miniTournamentId}` - Cập nhật thông tin kèo (bao gồm match_format)
- `POST /api/mini-tournaments/{id}/start-session` - Bắt đầu session với dữ liệu phân nhóm

### Testing
Test case cần kiểm tra:
1. ✅ Chọn `match_format=rank_pairing` khi chưa phân nhóm → Không báo lỗi
2. ✅ Chọn `match_format=mixed_gender` khi chưa phân nhóm → Không báo lỗi
3. ✅ Start session với phân nhóm không đủ → Báo lỗi đúng
4. ✅ Start session với phân nhóm đầy đủ → Generate matches thành công

### Fix bổ sung: `canUpdateMatchFormat()` logic

**Vấn đề phát hiện:**
Tournament chưa có trận đấu nào nhưng vẫn báo lỗi "Không thể thay đổi thể thức kèo đấu".

**Nguyên nhân:**
Method `canUpdateMatchFormat()` trong `MiniTournament.php` có logic không hợp lý:
- Kiểm tra format-specific rules trước khi kiểm tra "có trận đấu không"
- Nếu `match_format` không thuộc các format đã biết → return `false`

**Giải pháp:**
Di chuyển kiểm tra "có trận đấu không" lên **trước** các kiểm tra format-specific:

```php
// Check if any matches exist (regardless of current format)
$hasMatches = $this->matches()->exists();

// If no matches at all, allow changing format
if (!$hasMatches) {
    return true;
}
```

**Logic sau khi fix:**
1. ✅ Status = closed/cancelled → không cho đổi
2. ✅ `match_format = null` → cho đổi (lần đầu chọn)
3. ✅ **Không có trận đấu nào** → **cho đổi** (key fix!)
4. ✅ Có trận đấu:
   - Standard: kiểm tra `is_session_started`
   - Round Robin: kiểm tra có trận đấu hoàn thành với kết quả không

**File đã sửa:**
- `app/Models/MiniTournament.php` (method `canUpdateMatchFormat()`, dòng 1158-1195)

---

## Fix: Tab "Trận đấu" Mini Tournament hiển thị trống khi đã chọn thể thức xoay vòng partner

### Vấn đề
Khi user đã chọn thể thức `partner_rotation` cho Mini Tournament (và session đã chuyển sang `ongoing`), tab "Trận đấu" hiển thị trống trơn — không có lịch thi đấu, không có trận nào để click. Kèm theo nhiều Vue warn trong console:
- `Property "currentRadius" was accessed during render but is not defined on instance`
- `Failed to resolve component: CheckIcon / XMarkIcon / DeleteStaffModal`
- `Extraneous non-emits event listeners (created/updated) were passed to component`
- `Invalid prop: type check failed for prop "hasFee". Expected Boolean, got Number`
- `Extraneous non-props attributes (selected-club) were passed to component`

### Nguyên nhân
1. **Race condition giữa 2 watch trong `MiniMatchScheduleTab.vue`**: cả `watch(props.data, ..., { immediate: true })` và `watch(props.data?.match_format, ..., { immediate: true })` chạy đồng thời. Watch 2 ghi đè `sessionSubTab` thành `'format'` ngay sau khi Watch 1 set thành `'schedule'` (dựa trên `session_status === ONGOING`).

2. **Template bị ẩn do điều kiện `!data.can_update_match_format`**: Khi backend trả `can_update_match_format = true`, cả 2 template block chính (format selection + session sub-tabs) đều không render, dẫn đến không có gì hiển thị trong tab.

3. **Empty state khi session ongoing nhưng rounds rỗng**: Khi `rounds` có data nhưng `matches = []` (session vừa khởi tạo, chưa có trận nào), UI render nút vòng + thanh tiến độ 0% nhưng SessionScheduleRound trống — user không biết phải làm gì.

4. **Component registration thiếu**: `MiniTournamentDetail.vue` import `CheckIcon`, `XMarkIcon`, `DeleteStaffModal` nhưng KHÔNG đăng ký trong `components: {}` → Vue không resolve được → render warning.

5. **`emits` chưa khai báo**: `CreateMiniMatch.vue` và `UpdateMiniMatch.vue` emit các event (`created`, `updated`) nhưng không khai báo `emits: [...]` (UpdateMiniMatch còn viết sai thành `emit:`) → Vue 3 cảnh báo non-emits listener.

6. **Prop type mismatch**: `MiniTournamentSubmitReceiptModal` yêu cầu `hasFee: Boolean` nhưng parent truyền trực tiếp từ API (`0`/`1` thay vì `false`/`true`).

7. **Defensive check khi đọc `currentRadius`**: Khi render `InviteGroup`, một số prop phụ thuộc có thể undefined gây warning "not defined on instance".

### Giải pháp

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue`

**1. Thêm computed `hasAnyMatchInSchedule`** để phân biệt "có rounds nhưng rỗng" vs "có rounds có matches":
```javascript
const hasAnyMatchInSchedule = computed(() => {
    if (!Array.isArray(sessionSchedule.value)) return false
    return sessionSchedule.value.some(round =>
        Array.isArray(round?.matches) && round.matches.length > 0
    )
})
```

**2. Thêm function `openCreateMatchForCurrentRound`** để mở modal tạo trận khi rounds rỗng:
```javascript
const openCreateMatchForCurrentRound = () => {
    const round = selectedRoundData.value
    if (round?.round_number) {
        currentRound.value = round.round_number
    }
    showCreateMiniMatchModal.value = true
}
```

**3. Fix race condition ở watch `props.data?.match_format`**: KHÔNG ghi đè `sessionSubTab` khi session đã active (ONGOING/FINISHED) — watch thứ 2 chỉ set `'format'` khi `isSessionActive = false`:
```javascript
} else {
    // Session format selected
    const sessionStatus = props.data?.session_status
    const isSessionActive = sessionStatus === SESSION_STATUS.ONGOING ||
        sessionStatus === SESSION_STATUS.FINISHED
    const shouldSetFormatTab = !isSessionActive
    if (shouldSetFormatTab) {
        sessionSubTab.value = 'format'
    }
    if (newFormat === MATCH_FORMAT.PARTNER_ROTATION) {
        loadSessionSchedule(props.data.id)
    }
}
```

**4. Defensive `loadSessionSchedule`**: chấp nhận cả `round_number` lẫn `round` làm key, và ưu tiên `current_round` từ backend:
```javascript
const normalizedRounds = res.data.rounds.map((r, idx) => ({
    ...r,
    round_number: r.round_number ?? r.round ?? (idx + 1),
}))
sessionSchedule.value = normalizedRounds

const backendCurrentRound = Number(res.data.current_round)
if (Number.isFinite(backendCurrentRound) &&
    normalizedRounds.some(r => r.round_number === backendCurrentRound)) {
    currentRound.value = backendCurrentRound
}
```

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.html`

**1. Bỏ điều kiện `!data.can_update_match_format` ở 2 template block** để session sub-tabs luôn hiển thị khi format đã được chọn.

**2. Thêm empty state khi `sessionSchedule.length > 0 && !hasAnyMatchInSchedule`** (đã có rounds nhưng chưa có trận nào) với nút "Tạo trận đấu vòng X" cho creator/referee:

```html
<div v-else-if="sessionSchedule && sessionSchedule.length > 0 && !hasAnyMatchInSchedule"
     class="min-h-[240px] flex flex-col items-center justify-center gap-3 py-8">
    <svg class="w-12 h-12 text-gray-300" ...>...</svg>
    <div class="text-center max-w-sm">
        <p class="text-sm text-gray-700 font-medium mb-1">Lịch thi đấu đã được khởi tạo nhưng chưa có trận nào.</p>
        <p class="text-xs text-gray-500">
            Hệ thống đã chuẩn bị {{ sessionSchedule.length }} vòng đấu. Bây giờ hãy tạo các trận cho vòng hiện tại.
        </p>
    </div>
    <button v-if="props.isCreator || props.isReferee"
            @click="openCreateMatchForCurrentRound"
            class="mt-2 px-5 py-2.5 bg-[#D72D36] text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition">
        Tạo trận đấu vòng {{ currentRound }}
    </button>
</div>
```

#### File: `picki/resources/js/components/pages/mini-tournament/detail/MiniTournamentDetail.vue`

**1. Đăng ký components còn thiếu** trong `components: {}`:
```javascript
CheckIcon,
XMarkIcon,
DeleteStaffModal,
```

#### File: `picki/resources/js/components/pages/mini-tournament/detail/MiniTournamentDetail.html`

**1. Defensive props cho `InviteGroup`** — fallback giá trị mặc định:
```html
<InviteGroup v-model="showInviteModal"
             :data="inviteGroupData || []"
             :clubs="clubs || []"
             :active-scope="activeScope"
             :selected-club="selectedClub"
             :search-query="searchQuery || ''"
             :current-radius="currentRadius || 10"
             :current-club-id="selectedClub"
             :tournament-max-players="mini?.max_players || 0"
             :current-participants-count="allParticipants?.length || 0"
             ... />
```

**2. Ép kiểu Boolean cho `hasFee`**:
```html
<MiniTournamentSubmitReceiptModal
    v-model:isOpen="showSubmitPaymentModal"
    :miniId="mini.id"
    :hasFee="Boolean(mini?.has_fee)"
    @success="handlePaymentSubmitSuccess"
/>
```

#### File: `picki/resources/js/components/molecules/create-mini-match/CreateMiniMatch.vue`

**1. Khai báo `emits`** để Vue 3 nhận diện `created` là custom event:
```javascript
emits: ['update:modelValue', 'created'],
```

#### File: `picki/resources/js/components/molecules/update-mini-match/UpdateMiniMatch.vue`

**1. Sửa typo `emit:` → `emits:`** để Vue 3 nhận diện `updated` là custom event:
```javascript
emits: ['update:modelValue', 'updated'],
```

### Luồng hoạt động sau khi fix

**Trước fix:**
1. User chọn `partner_rotation` → `sessionSubTab` race condition → hiển thị tab "Thể thức" thay vì tab "Lịch thi đấu"
2. User vào tab "Trận đấu" ở MiniTournamentDetail → render `MiniMatchScheduleTab`
3. Template với `!data.can_update_match_format` không khớp → không render gì → tab trống

**Sau fix:**
1. User chọn `partner_rotation` → watch 1 set `sessionSubTab = 'schedule'` (vì ONGOING), watch 2 KHÔNG ghi đè
2. User vào tab "Trận đấu" → render session sub-tabs (Thể thức/Phân nhóm/Lịch thi đấu/BXH)
3. Nếu session rỗng → hiển thị empty state với nút "Tạo trận đấu vòng X" cho creator/referee
4. Nếu có matches → hiển thị danh sách trận như bình thường

### Files đã sửa
- `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue`
- `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.html`
- `picki/resources/js/components/pages/mini-tournament/detail/MiniTournamentDetail.vue`
- `picki/resources/js/components/pages/mini-tournament/detail/MiniTournamentDetail.html`
- `picki/resources/js/components/molecules/create-mini-match/CreateMiniMatch.vue`
- `picki/resources/js/components/molecules/update-mini-match/UpdateMiniMatch.vue`

### Testing
Test case cần kiểm tra:
1. ✅ Vào tab Trận đấu khi session ongoing → hiển thị đúng tab "Lịch thi đấu"
2. ✅ Session rỗng (chưa có trận nào) → hiển thị empty state với nút tạo trận
3. ✅ Session có matches → hiển thị danh sách trận bình thường
4. ✅ Console sạch không còn Vue warn
5. ✅ Khi đổi format từ STANDARD → partner_rotation, session sub-tabs vẫn hiển thị

---

## Fix: Cho phép đổi thể thức khi session đang diễn ra nhưng chưa có kết quả trận nào

### Vấn đề
Khi user đã chọn thể thức (ví dụ `partner_rotation`) và session đã chuyển sang `ongoing`, tab "Thể thức" trong Mini Tournament hiển thị dòng cố định "Session đang diễn ra..." — không cho phép đổi thể thức khác, kể cả khi **chưa có trận nào được lưu kết quả**.

Đây là UX không hợp lý vì:
- Khi session vừa khởi tạo (rounds có nhưng chưa có match nào), user không thể đổi thể thức dù chưa có dữ liệu lịch sử để mất
- Khi session đã có ít nhất 1 trận có kết quả → mới thực sự cần khóa để tránh mất lịch sử

### Giải pháp
Phân biệt 2 trạng thái dựa trên **có kết quả trận nào chưa**:
- **ONGOING + chưa có KQ** → cho phép đổi thể thức
- **ONGOING + đã có KQ** → khóa (không cho đổi)
- **FINISHED** → luôn khóa
- **PENDING_GROUP / READY / null** → cho phép đổi

### File đã sửa

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue`

**1. Thêm computed `hasCompletedMatchInSession`** — kiểm tra có trận nào đã có kết quả thực sự trong `sessionSchedule` (chỉ dựa trên score > 0, không dựa vào `match.status` vì backend có thể set status mặc định khi tạo trận):
```javascript
const hasCompletedMatchInSession = computed(() => {
    if (!Array.isArray(sessionSchedule.value) || sessionSchedule.value.length === 0) return false
    return sessionSchedule.value.some(round => {
        if (!Array.isArray(round?.matches) || round.matches.length === 0) return false
        return round.matches.some(match => {
            // Có results_by_sets với score thực sự > 0
            if (match.results_by_sets && Object.keys(match.results_by_sets).length > 0) {
                for (const set of Object.values(match.results_by_sets)) {
                    if (Array.isArray(set)) {
                        for (const r of set) {
                            if (r?.score != null && Number(r.score) > 0) return true
                        }
                    }
                }
            }
            // Có score_1 hoặc score_2 thực sự > 0
            const s1 = match.score_1
            const s2 = match.score_2
            if ((s1 != null && Number(s1) > 0) || (s2 != null && Number(s2) > 0)) {
                return true
            }
            return false
        })
    })
})
```

**2. Thêm computed `canChangeMatchFormat`** — quyết định có cho phép đổi thể thức không:
```javascript
const canChangeMatchFormat = computed(() => {
    if (!props.data?.match_format) return true
    if (effectiveSessionStatus.value === SESSION_STATUS.FINISHED) return false
    if (effectiveSessionStatus.value === SESSION_STATUS.ONGOING) {
        return !hasCompletedMatchInSession.value
    }
    return true
})
```

**3. Reset state khi user xác nhận đổi thể thức** trong `confirmFormatSelection()`:
```javascript
// Reset state trước khi đổi format để tránh hiển thị data cũ
sessionSchedule.value = []
sessionLeaderboardData.value = {}
currentRound.value = 1
playerGroups.value = {}
```

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.html`

**Sửa logic tab "Thể thức" trong session sub-tabs** — chia 2 case ONGOING:
```html
<!-- ONGOING + đã có kết quả: KHÔNG cho đổi thể thức -->
<div v-else-if="effectiveSessionStatus === SESSION_STATUS.ONGOING && hasCompletedMatchInSession"
     class="text-center py-8">
    <p class="text-[#6B6F80] text-sm">Session đang diễn ra và đã có kết quả — không thể đổi thể thức.</p>
</div>

<!-- ONGOING + chưa có kết quả: cho đổi thể thức -->
<div v-else-if="effectiveSessionStatus === SESSION_STATUS.ONGOING && !hasCompletedMatchInSession"
     class="space-y-3">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-900">Thay đổi thể thức thi đấu</h3>
        <button @click="sessionSubTab = 'schedule'" class="text-xs text-blue-600 hover:underline">
            Quay lại lịch thi đấu
        </button>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
        <p class="text-xs text-amber-700">
            Session đang diễn ra nhưng chưa có trận nào có kết quả. Bạn có thể đổi sang thể thức khác — các trận đã tạo (nếu có) sẽ bị xóa.
        </p>
    </div>

    <!-- 4 thẻ chọn thể thức: Tiêu chuẩn / Xoay vòng partner / Mix nam nữ / Ghép hạng A/B -->
    <!-- Mỗi thẻ gọi openFormatConfirm(format) -->
</div>

<!-- FINISHED -->
<div v-else-if="effectiveSessionStatus === SESSION_STATUS.FINISHED" class="text-center py-8">
    <p class="text-[#6B6F80] text-sm">Session đã kết thúc — không thể đổi thể thức.</p>
</div>
```

### Luồng hoạt động sau khi fix

| Trạng thái session | Có kết quả chưa? | Hành vi UI |
|---|---|---|
| Chưa có format | N/A | Hiển thị danh sách thể thức để chọn |
| `PENDING_GROUP` / `READY` / null | N/A | Hiển thị UI riêng (phân nhóm cho mixed/rank, danh sách trận cho partner_rotation) |
| `ONGOING` | ❌ Chưa | **Hiển thị danh sách thể thức để đổi** ✓ |
| `ONGOING` | ✅ Đã có | Hiển thị "Session đang diễn ra và đã có kết quả — không thể đổi thể thức" |
| `FINISHED` | N/A | Hiển thị "Session đã kết thúc — không thể đổi thể thức" |

### Testing
Test case cần kiểm tra:
1. ✅ Session `ongoing`, chưa có trận nào → vào tab Thể thức thấy 4 thẻ chọn format
2. ✅ User đổi từ `partner_rotation` sang `standard` → `sessionSchedule` reset, chuyển sang sub-tab "Trận đấu của bạn"
3. ✅ User đổi từ `partner_rotation` sang `mixed_gender` → reset, chuyển sang sub-tab "Phân nhóm"
4. ✅ Session `ongoing`, có 1 trận có KQ → vào tab Thể thức thấy text "không thể đổi"
5. ✅ Session `finished` → vào tab Thể thức thấy text "không thể đổi"

---

## Fix: `hasCompletedMatchInSession` trả về `true` sai khi session vừa khởi tạo (mixed_gender / rank_pairing)

### Vấn đề
Sau khi áp dụng fix trên, vẫn có bug: với thể thức `mixed_gender` (hoặc `rank_pairing`), sau khi user phân nhóm và bấm "Bắt đầu Session" nhưng **chưa hoàn tất trận nào**, tab "Thể thức" vẫn báo "Session đang diễn ra và đã có kết quả — không thể đổi thể thức".

### Nguyên nhân
Logic `hasCompletedMatchInSession` ban đầu có check `match.status` thuộc `['completed', 'going_on', 'waiting_confirm', 'disputed']`. Tuy nhiên backend có thể set `match.status` mặc định (không phải do user nhập điểm) ngay khi tạo trận — khi đó logic hiểu nhầm là "đã có kết quả".

### Giải pháp
Bỏ hoàn toàn việc dựa vào `match.status`. Chỉ xác định "đã có kết quả" khi:
- `results_by_sets` chứa ít nhất 1 set có score > 0, HOẶC
- `score_1 > 0` hoặc `score_2 > 0`

### File đã sửa

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue`

**Cập nhật computed `hasCompletedMatchInSession`** — chỉ check score thực sự > 0, bỏ check theo `match.status`:

```javascript
const hasCompletedMatchInSession = computed(() => {
    if (!Array.isArray(sessionSchedule.value) || sessionSchedule.value.length === 0) return false
    return sessionSchedule.value.some(round => {
        if (!Array.isArray(round?.matches) || round.matches.length === 0) return false
        return round.matches.some(match => {
            // Có results_by_sets với score thực sự > 0
            if (match.results_by_sets && Object.keys(match.results_by_sets).length > 0) {
                for (const set of Object.values(match.results_by_sets)) {
                    if (Array.isArray(set)) {
                        for (const r of set) {
                            if (r?.score != null && Number(r.score) > 0) return true
                        }
                    }
                }
            }
            // Có score_1 hoặc score_2 thực sự > 0
            const s1 = match.score_1
            const s2 = match.score_2
            if ((s1 != null && Number(s1) > 0) || (s2 != null && Number(s2) > 0)) {
                return true
            }
            return false
        })
    })
})
```

### Luồng hoạt động sau khi fix

| Thể thức | Sau phân nhóm + start session | Trạng thái | Kết quả |
|---|---|---|---|
| `mixed_gender` | Chưa hoàn tất trận nào | `ONGOING`, `sessionSchedule` rỗng hoặc matches chưa có score | Tab "Thể thức" hiển thị 4 thẻ chọn format ✓ |
| `rank_pairing` | Chưa hoàn tất trận nào | `ONGOING`, chưa có score | Tab "Thể thức" hiển thị 4 thẻ chọn format ✓ |
| `partner_rotation` | Chưa hoàn tất trận nào | `ONGOING`, chưa có score | Tab "Thể thức" hiển thị 4 thẻ chọn format ✓ |
| Bất kỳ | Đã có 1 trận có KQ thực sự | `ONGOING`, score > 0 | Tab "Thể thức" hiển thị "không thể đổi" ✓ |

---

## Fix: Xóa matches cũ khi đổi thể thức (session format → standard hoặc giữa các session formats)

### Vấn đề
Khi user đổi từ thể thức session (`mixed_gender`/`rank_pairing`/`partner_rotation`) sang `standard`, các trận đấu của thể thức cũ vẫn hiển thị trong tab "Trận đấu" vì:
1. Backend không tự xóa matches khi đổi format
2. Backend validation `canUpdateMatchFormat()` reject 422 nếu còn matches trong DB
3. Response structure khác nhau: standard trả `matches: []` (flat), session format trả `rounds: [{matches: [...]}]`

### Giải pháp
Trong `confirmFormatSelection()`, trước khi gọi API update `match_format`:
1. Gọi `getListMiniMatches` để lấy TẤT CẢ matches (cả standard `matches[]` và session `rounds[].matches[]`)
2. Gọi `deleteMiniMatches` để xóa matches cũ (throw lỗi nếu fail)
3. Update format
4. Reload matches mới (sẽ rỗng vì standard chưa có trận nào)

### File đã sửa

#### File: `picki/resources/js/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue`

**Cập nhật `confirmFormatSelection()`** — extract matches từ cả 2 cấu trúc response:

```javascript
// Xóa matches của format cũ trước khi đổi sang format mới
if (previousFormat && props.data?.id && previousFormat !== selectedFormat.value) {
    const allRes = await MiniMatchService.getListMiniMatches(props.data.id, { page: 1 })
    let allMatches = []

    // Standard format: trả { matches: [...] } (flat)
    if (Array.isArray(allRes?.matches) && allRes.matches.length > 0) {
        allMatches = allRes.matches
    }
    // Session format (mixed_gender/rank_pairing/partner_rotation): trả { rounds: [{ matches: [...] }] }
    else if (Array.isArray(allRes?.rounds) && allRes.rounds.length > 0) {
        for (const round of allRes.rounds) {
            if (Array.isArray(round?.matches)) {
                allMatches.push(...round.matches)
            }
        }
    }

    if (allMatches.length > 0) {
        const idsToDelete = allMatches.map(m => m?.id).filter(Boolean)
        if (idsToDelete.length > 0) {
            await MiniMatchService.deleteMiniMatches({ ids: idsToDelete })
        }
    }
}

await updateMiniTournamentByClub(props.clubId, props.data.id, { match_format: selectedFormat.value })
```

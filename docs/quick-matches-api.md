# Quick Matches API Documentation

> **Base URL:** `/api`  
> **Authentication:** Bearer Token (JWT) — trừ endpoint `GET /quick-matches/qr/{qr_code}` không cần auth.

---

## Mục lục

1. [Tổng quan](#1-tổng-quan)
2. [Data Models](#2-data-models)
3. [Endpoints](#3-endpoints)
4. [Response Format](#4-response-format)
5. [Business Logic & Quy tắc nghiệp vụ](#5-business-logic--quy-tắc-nghiệp-vụ)
6. [Database Schema](#6-database-schema)

---

## 1. Tổng quan

Quick Match là tính năng tạo trận đấu nhanh giữa 2 đội (mỗi đội tối đa 2 người). Luồng hoạt động:

```
1. Người tạo (creator) POST /quick-matches  →  Tạo trận với status "pending"
2. QR code được sinh ra cho trận pending
3. Team B quét QR → POST /quick-matches/confirm/{qr_code}  →  status chuyển sang "confirmed"
4. Một trong các player cập nhật điểm → PUT /quick-matches/{id}/score
   →  Nếu status = "confirmed" → tự động chuyển sang "completed"
```

**Lưu ý đặc biệt:** Nếu người tạo là **Super Admin** (`is_super_admin = true` trong bảng `users`):
- Trận được tạo ngay với status `"confirmed"` (không cần QR confirm)
- Không sinh QR code
- Lịch sử thi đấu (`match_histories`) được tạo ngay lúc tạo trận

### HTTP Status Codes

| Code | Mô tả |
|------|--------|
| 200 | Thành công |
| 201 | Tạo mới thành công |
| 400 | Bad Request (validation fails, trạng thái không hợp lệ) |
| 401 | Unauthorized (chưa đăng nhập / token không hợp lệ) |
| 403 | Forbidden (không có quyền thực hiện) |
| 404 | Không tìm thấy tài nguyên |
| 422 | Validation Error |

---

## 2. Data Models

### QuickMatch Model

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| `id` | bigint | Primary key |
| `name` | string? | Tên trận đấu (tùy chọn, max 255) |
| `note` | text? | Ghi chú (tùy chọn, max 1000) |
| `team_a` | JSON | Array chứa user_id của đội A (1–2 người) |
| `team_b` | JSON | Array chứa user_id của đội B (1–2 người) |
| `match_type` | enum | `"rank"` (mặc định) hoặc `"casual"` |
| `qr_code` | string? | Mã QR ngẫu nhiên 32 ký tự (null nếu Super Admin tạo) |
| `status` | enum | `"pending"`, `"confirmed"`, `"completed"` |
| `score` | JSON? | `{"team_a": [int], "team_b": [int]}` |
| `created_by` | bigint | ID của user tạo trận |
| `scheduled_at` | datetime? | Thời gian dự kiến thi đấu |
| `competition_location_id` | bigint? | FK đến bảng `competition_locations` |

#### Constants

```php
// Status
QuickMatch::STATUS_PENDING    = 'pending';     // Chờ xác nhận
QuickMatch::STATUS_CONFIRMED  = 'confirmed';  // Đã xác nhận
QuickMatch::STATUS_COMPLETED  = 'completed';   // Hoàn tất

// Match Type
QuickMatch::MATCH_TYPE_RANK   = 'rank';        // Xếp hạng
QuickMatch::MATCH_TYPE_CASUAL = 'casual';      // Vui chơi
```

### MatchHistory Model

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| `id` | bigint | Primary key |
| `user_id` | bigint | ID người chơi |
| `quick_match_id` | bigint | ID trận đấu |
| `team_side` | string? | `"team_a"` hoặc `"team_b"` |
| `played_at` | datetime? | Thời gian thi đấu |

**Unique constraint:** `(user_id, quick_match_id)` — mỗi user chỉ có 1 record cho 1 trận.

---

## 3. Endpoints

---

### POST `/api/quick-matches` — Tạo trận đấu nhanh

**Auth:** Required (Bearer Token)  
**Method:** `POST`

#### Request Body

```json
{
  "name": "Trận chung kết",
  "note": "Trận đấu quyết định ngôi vô địch",
  "match_type": "rank",
  "team_a": [1, 5],
  "team_b": [3, 8],
  "scheduled_at": "2026-05-20T18:00:00Z",
  "competition_location_id": 1
}
```

| Trường | Bắt buộc | Kiểu | Quy tắc |
|--------|-----------|------|---------|
| `team_a` | **Yes** | array | 1–2 phần tử, mỗi phần tử là `user_id` tồn tại trong bảng `users` |
| `team_b` | **Yes** | array | 1–2 phần tử, mỗi phần tử là `user_id` tồn tại trong bảng `users` |
| `name` | No | string | max 255 ký tự |
| `note` | No | string | max 1000 ký tự |
| `match_type` | No | string | `"rank"` hoặc `"casual"` (mặc định: `"rank"`) |
| `scheduled_at` | No | date | ISO 8601 format |
| `competition_location_id` | No | integer | Phải tồn tại trong bảng `competition_locations` |

#### Response — 201 Created

```json
{
  "status": true,
  "message": "Tạo trận đấu nhanh thành công",
  "data": {
    "id": 12,
    "name": "Trận chung kết",
    "note": "Trận đấu quyết định ngôi vô địch",
    "match_type": "rank",
    "status": "pending",
    "created_by": 1,
    "team_a": {
      "user_ids": [1, 5],
      "users": [
        {
          "id": 1,
          "full_name": "Nguyễn Văn A",
          "visibility": "public",
          "avatar_url": "https://...",
          "thumbnail": "https://...",
          "gender": "male",
          "gender_text": "Nam",
          "play_times": [...],
          "sports": [
            {
              "sport_id": 1,
              "sport_icon": "🏓",
              "sport_name": "Pickleball",
              "scores": { "personal_score": "0.000", "dupr_score": "3.500", "vndupr_score": "3.750" },
              "total_matches": 45,
              "total_tournaments": 5,
              "total_mini_tournaments": 12,
              "total_prizes": 3,
              "win_rate": 68.5,
              "performance": 1.2
            }
          ],
          "is_manager": false,
          "rank_in_club": null,
          "is_anchor": true,
          "is_verify": true,
          "is_guest": false,
          "guest_name": null,
          "guest_avatar": null
        }
      ]
    },
    "team_b": {
      "user_ids": [3, 8],
      "users": [...]
    },
    "score": {
      "team_a": [],
      "team_b": []
    },
    "qr_code_url": "http://app.com/api/quick-matches/confirm/abc123...",
    "creator": {
      "id": 1,
      "name": "Nguyễn Văn A",
      "avatar_url": "https://...",
      "gender": "male"
    },
    "is_super_admin_created": false,
    "scheduled_at": "2026-05-20T18:00:00+07:00",
    "competition_location": {
      "id": 1,
      "name": "Sân Pickleball Quận 1",
      "latitude": 10.7769,
      "longitude": 106.7009,
      "image": "https://...",
      "address": "123 Nguyễn Huệ, Q1, TP.HCM",
      "phone": "0901234567",
      "opening_time": "06:00",
      "closing_time": "22:00",
      "note_booking": "...",
      "website": "https://...",
      "location": { "id": 1, "name": "TP.HCM", ... },
      "sports": [...],
      "yard_types": [...],
      "facilities": [...]
    },
    "created_at": "2026-05-18T10:30:00+07:00",
    "updated_at": "2026-05-18T10:30:00+07:00"
  }
}
```

#### Business Logic

- User đang authenticate sẽ trở thành `created_by`
- **Super Admin detection:** Kiểm tra field `is_super_admin` trong bảng `users` (truthy value)
- Nếu user là **Super Admin** (`is_super_admin = true`): status = `"confirmed"`, không sinh QR, tạo `match_histories` ngay
- Nếu user **thường** (`is_super_admin = false` hoặc không có): status = `"pending"`, sinh `qr_code` (32 ký tự ngẫu nhiên)
- Tất cả thao tác DB được wrap trong transaction

---

### GET `/api/quick-matches/{id}` — Chi tiết trận đấu

**Auth:** Required (Bearer Token)  
**Method:** `GET`

#### URL Parameters

| Tham số | Kiểu | Mô tả |
|---------|------|--------|
| `id` | integer | ID của trận đấu |

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Thành công",
  "data": {
    "id": 12,
    "name": "Trận chung kết",
    "match_type": "rank",
    "status": "pending",
    "team_a": { "user_ids": [1, 5], "users": [...] },
    "team_b": { "user_ids": [3, 8], "users": [...] },
    "score": { "team_a": [], "team_b": [] },
    "qr_code_url": "http://app.com/api/quick-matches/confirm/abc123...",
    "creator": {...},
    "scheduled_at": "2026-05-20T18:00:00+07:00",
    "competition_location": {...},
    "created_at": "2026-05-18T10:30:00+07:00",
    "updated_at": "2026-05-18T10:30:00+07:00"
  }
}
```

#### Response — 404 Not Found

```json
{
  "status": false,
  "message": "Không tìm thấy trận đấu.",
  "data": null
}
```

---

### PUT `/api/quick-matches/{id}/score` — Cập nhật điểm

**Auth:** Required (Bearer Token)  
**Method:** `PUT`

#### URL Parameters

| Tham số | Kiểu | Mô tả |
|---------|------|--------|
| `id` | integer | ID của trận đấu |

#### Request Body

```json
{
  "score": {
    "team_a": [11, 9],
    "team_b": [8, 11]
  }
}
```

| Trường | Bắt buộc | Kiểu | Quy tắc |
|--------|-----------|------|---------|
| `score` | **Yes** | object | |
| `score.team_a` | **Yes** | array | Mỗi phần tử là số nguyên >= 0 |
| `score.team_b` | **Yes** | array | Mỗi phần tử là số nguyên >= 0 |

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Cập nhật điểm thành công.",
  "data": {
    "id": 12,
    "status": "completed",
    "score": {
      "team_a": [11, 9],
      "team_b": [8, 11]
    },
    ...
  }
}
```

#### Business Logic

- **Authorization:** Chỉ player trong trận (thuộc `team_a` hoặc `team_b`) mới được cập nhật điểm
- Khi score được cập nhật:
  - Nếu `status` hiện tại là `"pending"` → giữ nguyên `"pending"` (chờ xác nhận)
  - Nếu `status` hiện tại là `"confirmed"` → tự động chuyển sang `"completed"`
- Nếu `status` đã là `"completed"` → reject với 400

#### Error Responses

| Code | Message |
|------|---------|
| 403 | Bạn không có quyền cập nhật điểm trận đấu này. |
| 400 | Trận đấu đã hoàn tất, không thể cập nhật điểm. |

---

### POST `/api/quick-matches/confirm/{qr_code}` — Xác nhận qua QR

**Auth:** Required (Bearer Token)  
**Method:** `POST`

#### URL Parameters

| Tham số | Kiểu | Mô tả |
|---------|------|--------|
| `qr_code` | string | Mã QR code (32 ký tự) |

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Xác nhận trận đấu thành công.",
  "data": {
    "id": 12,
    "status": "confirmed",
    ...
  }
}
```

#### Business Logic

- **Authorization:** Chỉ user thuộc `team_b` mới được xác nhận (người tạo đã ở team A)
- Khi xác nhận thành công:
  - `status` chuyển từ `"pending"` → `"confirmed"`
  - Tạo `match_histories` cho tất cả players (cả team A và team B)
- Nếu user không thuộc `team_b` → 403
- Nếu trận đã `"confirmed"` → 400 (đã xác nhận rồi)
- Nếu trận đã `"completed"` → 400 (đã hoàn tất rồi)

#### Error Responses

| Code | Message |
|------|---------|
| 403 | Bạn không có quyền xác nhận trận đấu này. |
| 400 | Trận đấu đã được xác nhận trước đó. |
| 400 | Trận đấu đã hoàn tất, không thể xác nhận. |

---

### GET `/api/quick-matches/qr/{qr_code}` — Preview QR

**Auth:** Không cần (public endpoint)  
**Method:** `GET`

> Endpoint này dùng để app quét QR preview thông tin trận trước khi confirm.

#### URL Parameters

| Tham số | Kiểu | Mô tả |
|---------|------|--------|
| `qr_code` | string | Mã QR code (32 ký tự) |

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Thành công",
  "data": {
    "quick_match_id": 12,
    "match_name": "Trận chung kết",
    "match_type": "rank",
    "status": "pending",
    "competition_location": {
      "id": 1,
      "name": "Sân Pickleball Quận 1",
      "latitude": 10.7769,
      "longitude": 106.7009,
      "image": "https://...",
      "address": "123 Nguyễn Huệ, Q1, TP.HCM",
      "location": { "id": 1, "name": "TP.HCM", ... },
      "sports": [...],
      "yard_types": [...],
      "facilities": [...]
    }
  }
}
```

#### Response — 404 Not Found

```json
{
  "status": false,
  "message": "Không tìm thấy trận đấu với mã này.",
  "data": null
}
```

---

## 4. Response Format

Tất cả API đều trả về format chung qua `ResponseHelper::success()` và `ResponseHelper::error()`:

### Success Response

```json
{
  "status": true,
  "message": "<thông điệp thành công>",
  "data": { ... }
}
```

### Error Response

```json
{
  "status": false,
  "message": "<thông điệp lỗi>",
  "data": null
}
```

### QuickMatchResource — Response Fields

| Field | Mô tả |
|-------|--------|
| `id` | ID trận đấu |
| `name` | Tên trận đấu |
| `note` | Ghi chú |
| `match_type` | Loại trận: `"rank"` hoặc `"casual"` |
| `status` | Trạng thái: `"pending"`, `"confirmed"`, `"completed"` |
| `created_by` | ID người tạo |
| `team_a.user_ids` | Array user_id đội A |
| `team_a.users` | Array UserListResource (giống Tournament) — gồm: `id`, `full_name`, `visibility`, `avatar_url`, `thumbnail`, `gender`, `gender_text`, `play_times`, `sports` (scores, stats) |
| `team_b.user_ids` | Array user_id đội B |
| `team_b.users` | Array UserListResource (giống Tournament) — gồm: `id`, `full_name`, `visibility`, `avatar_url`, `thumbnail`, `gender`, `gender_text`, `play_times`, `sports` (scores, stats) |
| `score.team_a` | Array điểm các set đội A |
| `score.team_b` | Array điểm các set đội B |
| `qr_code_url` | URL để confirm qua QR, null nếu Super Admin tạo |
| `creator` | Inline object: `{id, name, avatar_url, gender}` — bỏ `UserResource` đầy đủ |
| `is_super_admin_created` | Boolean — có phải Super Admin tạo không |
| `scheduled_at` | Thời gian dự kiến (ISO 8601) |
| `competition_location` | CompetitionLocationResource: `id`, `name`, `latitude`, `longitude`, `image`, `address`, `phone`, `opening_time`, `closing_time`, `note_booking`, `website`, `location`, `sports`, `yard_types`, `facilities` |
| `created_at` | Thời gian tạo (ISO 8601) |
| `updated_at` | Thời gian cập nhật (ISO 8601) |

---

## 5. Business Logic & Quy tắc nghiệp vụ

### Luồng trận đấu (State Machine)

```
[pending] ──(team_b xác nhận QR)──→ [confirmed] ──(cập nhật score)──→ [completed]
    │                                      │
    └──(Super Admin tạo)──────────────────┘
```

### Quy tắc quan trọng

1. **Tạo trận:** Mỗi đội 1–2 người, tổng tối thiểu 2 người, tối đa 4 người
2. **QR Code:** Chỉ sinh khi user thường tạo trận (status `pending`). Super Admin tạo trực tiếp `confirmed`.
3. **Xác nhận QR:** Chỉ member của `team_b` mới được xác nhận
4. **Cập nhật điểm:** Bất kỳ player nào trong trận đều được cập nhật, nhưng chỉ cập nhật được khi trận chưa `completed`
5. **Match Histories:** Được tạo khi:
   - Super Admin tạo trận (ngay lúc tạo)
   - Team B xác nhận QR thành công (ngay lúc confirm)
6. **UpdateOrCreate:** `match_histories` dùng `updateOrCreate` nên nếu user chơi lại trận cũ, chỉ cập nhật `team_side` và `played_at`

### Authorization Matrix

| Action | Creator (team A) | Team B member | Other player | Public |
|--------|-----------------|---------------|--------------|--------|
| Tạo trận | ✅ | ❌ | ❌ | ❌ |
| Xem chi tiết | ✅ | ✅ | ✅ | ❌ |
| Xác nhận QR | ❌ | ✅ | ❌ | ❌ |
| Cập nhật điểm | ✅ | ✅ | ❌ | ❌ |
| Preview QR | ✅ | ✅ | ✅ | ✅ |

---

## 6. Database Schema

### Table: `quick_matches`

```sql
CREATE TABLE quick_matches (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NULL,
    note TEXT NULL,
    team_a JSON NOT NULL,          -- [user_id, user_id]
    team_b JSON NOT NULL,          -- [user_id, user_id]
    match_type ENUM('rank','casual') DEFAULT 'rank',
    qr_code VARCHAR(255) UNIQUE NULL,
    status ENUM('pending','confirmed','completed') DEFAULT 'pending',
    score JSON NULL,               -- {"team_a": [11, 9], "team_b": [8, 11]}
    created_by BIGINT UNSIGNED NOT NULL,
    scheduled_at TIMESTAMP NULL,
    competition_location_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    INDEX idx_qr_code (qr_code),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_competition_location_id (competition_location_id)
);
```

### Table: `match_histories`

```sql
CREATE TABLE match_histories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    quick_match_id BIGINT UNSIGNED NOT NULL,
    team_side VARCHAR(255) NULL,   -- 'team_a' or 'team_b'
    played_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE INDEX idx_user_match (user_id, quick_match_id),
    INDEX idx_user_id (user_id),
    INDEX idx_quick_match_id (quick_match_id)
);
```

---

## File Reference

| Loại | Đường dẫn |
|------|-----------|
| Controller | `picki/app/Http/Controllers/QuickMatchController.php` |
| Resource | `picki/app/Http/Resources/QuickMatchResource.php` |
| Model (QuickMatch) | `picki/app/Models/QuickMatch.php` |
| Model (MatchHistory) | `picki/app/Models/MatchHistory.php` |
| Routes | `picki/routes/api.php` |
| Migration (quick_matches) | `picki/database/migrations/2026_05_18_000001_create_quick_matches_table.php` |
| Migration (match_histories) | `picki/database/migrations/2026_05_18_000002_create_match_histories_table.php` |

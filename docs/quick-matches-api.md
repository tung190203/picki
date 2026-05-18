
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

### GET `/api/quick-matches/qr/{qr_code}` — Preview QR

**Auth:** Không cần (public endpoint)  
**Method:** `GET`

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

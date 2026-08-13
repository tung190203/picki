## 1. Estimate Recipients

Estimate số lượng người dùng đủ điều kiện trước khi gửi. Dùng cho realtime counter ở FE.

**Endpoint:** `POST /api/admin/push-notifications/estimate-recipients`

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `recipient_type` | enum | Yes | `ALL` / `CLUB` / `ACTIVITY` / `USERS` |
| `recipient_config` | object | Yes | Config tùy theo `recipient_type` |

#### recipient_config theo từng type

**ALL:**
```json
{ "recipient_type": "ALL", "recipient_config": {} }
```

**CLUB:**
```json
{ "recipient_type": "CLUB", "recipient_config": { "club_id": 1 } }
```

**ACTIVITY (HOT / WARM / COLD):**
```json
{ "recipient_type": "ACTIVITY", "recipient_config": { "level": "HOT" } }
```

**USERS:**
```json
{ "recipient_type": "USERS", "recipient_config": { "user_ids": [1, 2, 3] } }
```

### Response

```json
{
  "status": true,
  "message": "Success",
  "data": {
    "recipient_type": "ALL",
    "estimated_recipient_count": 1532
  }
}
```

### Logic chung (áp dụng cho mọi recipient_type)
User được tính vào `estimated_recipient_count` khi:
- `is_banned = false`
- `is_guest = false`
- `is_merged = false`
- `deleted_at IS NULL`
- Có ít nhất 1 device token với `is_enabled = true`

### HOT / WARM / COLD
- **HOT:** `last_active_at >= now() - 7 days`
- **WARM:** `last_active_at` từ 7 đến 30 ngày trước
- **COLD:** `last_active_at < now() - 30 days` hoặc `NULL`

---

## 2. Preview Campaign

Trả về preview data để hiển thị confirm modal.

**Endpoint:** `POST /api/admin/push-notifications/preview`

### Request Body
Cùng validation như `Create Campaign`.

### Response

```json
{
  "status": true,
  "message": "Success",
  "data": {
    "title": "Giải đấu mùa hè 2026",
    "content": "Đăng ký ngay để nhận ưu đãi",
    "action_type": "TOURNAMENT",
    "action_id": 123,
    "recipient_type": "ACTIVITY",
    "recipient_config": { "level": "HOT" },
    "recipient_label": "Nóng (HOT) - Hoạt động trong vòng 7 ngày qua",
    "estimated_recipient_count": 234,
    "warnings": ["Hoạt động trong vòng 7 ngày qua"],
    "send_type": "SCHEDULED",
    "scheduled_at": "2026-08-15T10:00:00+07:00"
  }
}
```

---

## 3. Send Test Notification

Gửi notification thử nghiệm cho **chính admin hiện tại** (không nhận `user_id` từ request).

**Endpoint:** `POST /api/admin/push-notifications/test`

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Max 50 chars |
| `content` | string | Yes | Max 150 chars |
| `image` | file | No | jpeg/png/jpg/webp, max 5MB |
| `action_type` | enum | No | `NONE` / `MATCH` / `TOURNAMENT` / `CLUB` |
| `action_id` | int | No | Required khi `action_type != NONE` |

### Response

```json
{
  "status": true,
  "message": "Đã gửi thông báo thử",
  "data": {
    "devices_count": 2,
    "success": 2,
    "failed": 0
  }
}
```

### Errors

- `422 - Tài khoản admin chưa đăng ký thiết bị để nhận thông báo thử.` (admin không có device enabled)

---

## 4. Create Campaign (Send)

Tạo campaign + upload ảnh (nếu có) + dispatch job (nếu IMMEDIATE).

**Endpoint:** `POST /api/admin/push-notifications`

### Request Body (multipart/form-data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Max 50 chars |
| `content` | string | Yes | Max 150 chars |
| `image` | file | No | jpeg/png/jpg/webp, max 5MB |
| `action_type` | enum | Yes | `NONE` / `MATCH` / `TOURNAMENT` / `CLUB` |
| `action_id` | int | Cond | Required when `action_type != NONE` |
| `recipient_type` | enum | Yes | `ALL` / `CLUB` / `ACTIVITY` / `USERS` |
| `recipient_config` | JSON string | Yes | Xem section 1 để biết structure |
| `send_type` | enum | Yes | `IMMEDIATE` / `SCHEDULED` |
| `scheduled_at` | ISO 8601 datetime | Cond | Required when `send_type = SCHEDULED`, phải sau `now()` |

### Response

```json
{
  "status": true,
  "message": "Đã tạo chiến dịch gửi thông báo",
  "data": {
    "id": 42,
    "title": "Giải đấu mùa hè 2026",
    "content": "Đăng ký ngay",
    "image_url": "https://.../storage/admin-push-notifications/abc.jpg",
    "action_type": "TOURNAMENT",
    "action_id": 123,
    "recipient_type": "ALL",
    "recipient_config": {},
    "recipient_label": "Tất cả người dùng",
    "send_type": "IMMEDIATE",
    "scheduled_at": null,
    "sent_at": null,
    "status": "PROCESSING",
    "status_label": "Đang gửi",
    "status_color": "yellow",
    "estimated_recipient_count": 1532,
    "actual_recipient_count": null,
    "success_count": null,
    "failure_count": null,
    "warnings": ["Thông báo sẽ được gửi cho TẤT CẢ người dùng đang hoạt động."],
    "created_by": 1,
    "creator_name": "Super Admin",
    "created_at": "2026-08-13T18:00:00+07:00"
  }
}
```

### Validation Errors

- `422` với `errors.title` — Title > 50 chars
- `422` với `errors.action_id` — Action ID không tồn tại hoặc required
- `422` với `errors.scheduled_at` — Phải sau thời điểm hiện tại (khi SCHEDULED)
- `422` với `errors.recipient_config.club_id` — CLB không tồn tại
- `422` với `errors.recipient_config.user_ids` — Empty / > 1000 users

---

## 5. List Campaigns (History)

Lấy danh sách campaign đã tạo, có filter và pagination.

**Endpoint:** `GET /api/admin/push-notifications`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Default: 1 |
| `limit` | int | Default: 15, max: 100 |
| `status` | enum | `DRAFT` / `SCHEDULED` / `PROCESSING` / `SENT` / `PARTIAL` / `FAILED` / `CANCELLED` |
| `recipient_type` | enum | `ALL` / `CLUB` / `ACTIVITY` / `USERS` |
| `search` | string | Tìm theo title hoặc content |
| `date_from` | date | Filter `created_at >= date_from` |
| `date_to` | date | Filter `created_at <= date_to` |

### Response

```json
{
  "status": true,
  "data": [
    {
      "id": 42,
      "title": "Giải đấu mùa hè",
      "content": "Đăng ký ngay",
      "image_url": "https://...",
      "action_type": "TOURNAMENT",
      "action_id": 123,
      "recipient_type": "ALL",
      "send_type": "IMMEDIATE",
      "scheduled_at": null,
      "sent_at": "2026-08-13T18:00:05+07:00",
      "status": "SENT",
      "status_label": "Đã gửi",
      "status_color": "green",
      "estimated_recipient_count": 1532,
      "actual_recipient_count": 1532,
      "success_count": 1530,
      "failure_count": 2,
      "created_by": 1,
      "creator_name": "Super Admin",
      "created_at": "2026-08-13T18:00:00+07:00"
    }
  ],
  "meta": {
    "message": "Success",
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

---

## 6. Campaign Detail

Lấy chi tiết 1 campaign bao gồm `recipient_label`, `warnings`, `metadata`.

**Endpoint:** `GET /api/admin/push-notifications/{id}`

### Response
Cùng structure như Create response nhưng có thêm các field:
- `recipient_label`: "Tất cả người dùng" / "CLB Picki Hà Nội - 234 thành viên" / etc.
- `warnings`: array các cảnh báo
- `metadata`: object chứa thông tin thêm (e.g. invalid_tokens_cleaned)
- `error_message`: lý do failure (nếu có)
- `creator`: object chi tiết admin tạo campaign

### Errors

- `404 - Chiến dịch không tồn tại`

---

## 7. Lookup Clubs

Search clubs cho dropdown khi `recipient_type = CLUB`.

**Endpoint:** `GET /api/admin/push-notifications/lookup/clubs`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `keyword` | string | Tìm theo tên |
| `page` | int | Default: 1 |
| `limit` | int | Default: 20, max: 50 |

### Response

```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "Picki Hà Nội",
      "logo_url": "https://..."
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

---

## 8. Lookup Users

Search users cho dropdown khi `recipient_type = USERS`.

**Endpoint:** `GET /api/admin/push-notifications/lookup/users`

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `keyword` | string | Tìm theo full_name, phone, email, hoặc id |
| `page` | int | Default: 1 |
| `limit` | int | Default: 20, max: 50 |

### Response

```json
{
  "status": true,
  "data": [
    {
      "id": 5,
      "name": "Nguyễn Văn A",
      "avatar_url": "https://...",
      "phone": "0901234567",
      "email": "user@example.com",
      "is_online": true
    }
  ],
  "meta": { ... }
}
```

---

## Status Lifecycle

```
       IMMEDIATE                  SCHEDULED + scheduled_at <= now()
          ↓                                    ↓
    PROCESSING  ← ────────────────────  PROCESSING  (idempotent atomic claim)
          ↓
    ┌─────┴──────┬────────────┐
    ↓            ↓            ↓
   SENT       PARTIAL       FAILED
 (100% ok)   (mixed)      (0% ok OR 0 devices)
```

**Idempotency:** Job dùng atomic UPDATE `WHERE status IN ('SCHEDULED','DRAFT','PROCESSING')` để tránh 2 workers cùng xử lý.

---

## Curl Examples

### 1. Estimate All

```bash
curl -X POST http://localhost:8000/api/admin/push-notifications/estimate-recipients \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"recipient_type":"ALL","recipient_config":{}}'
```

### 2. Preview HOT Campaign

```bash
curl -X POST http://localhost:8000/api/admin/push-notifications/preview \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title":"Giải HOT tháng 8",
    "content":"Đăng ký ngay",
    "action_type":"TOURNAMENT",
    "action_id":123,
    "recipient_type":"ACTIVITY",
    "recipient_config":{"level":"HOT"},
    "send_type":"IMMEDIATE"
  }'
```

### 3. Send Test

```bash
curl -X POST http://localhost:8000/api/admin/push-notifications/test \
  -H "Authorization: Bearer {admin_token}" \
  -F "title=Test thử" \
  -F "content=Hello world"
```

### 4. Create + Send Immediate (with Image)

```bash
curl -X POST http://localhost:8000/api/admin/push-notifications \
  -H "Authorization: Bearer {admin_token}" \
  -F "title=Giải mùa hè" \
  -F "content=Đăng ký ngay để nhận ưu đãi" \
  -F "image=@/path/to/image.jpg" \
  -F "action_type=TOURNAMENT" \
  -F "action_id=123" \
  -F "recipient_type=CLUB" \
  -F 'recipient_config={"club_id":1}' \
  -F "send_type=IMMEDIATE"
```

### 5. Create Scheduled

```bash
curl -X POST http://localhost:8000/api/admin/push-notifications \
  -H "Authorization: Bearer {admin_token}" \
  -F "title=Giải mùa hè" \
  -F "content=Đăng ký ngay" \
  -F "action_type=NONE" \
  -F "recipient_type=ALL" \
  -F 'recipient_config={}' \
  -F "send_type=SCHEDULED" \
  -F "scheduled_at=2026-08-20T10:00:00+07:00"
```

### 6. List History

```bash
curl "http://localhost:8000/api/admin/push-notifications?page=1&limit=15&status=SENT" \
  -H "Authorization: Bearer {admin_token}"
```

### 7. Detail

```bash
curl http://localhost:8000/api/admin/push-notifications/42 \
  -H "Authorization: Bearer {admin_token}"
```

### 8. Lookup Clubs

```bash
curl "http://localhost:8000/api/admin/push-notifications/lookup/clubs?keyword=Picki" \
  -H "Authorization: Bearer {admin_token}"
```

### 9. Lookup Users

```bash
curl "http://localhost:8000/api/admin/push-notifications/lookup/users?keyword=Nguyen" \
  -H "Authorization: Bearer {admin_token}"
```

---

## Notes

- Mọi campaign đều được log vào `audit_logs` với action `create_admin_push_notification` (hoặc `send_admin_push_test` cho test).
- Job chạy idempotent: scheduler mỗi phút quét campaigns `SCHEDULED` với `scheduled_at <= now()`.
- Image được lưu ở `Storage::disk('public')` tại `admin-push-notifications/`.
- FCM sử dụng HTTP v1 API với batch multicast (max 500 tokens/request, internally chunked).
- Device tokens trả về lỗi `UNREGISTERED` / `INVALID_ARGUMENT` / `NOT_FOUND` sẽ tự động bị xóa.
- `last_active_at` được sử dụng cho HOT/WARM/COLD segmentation — có thể trả 0 cho HOT ngay khi user chưa từng active (cần document rõ cho FE).
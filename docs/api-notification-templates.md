# Notification Templates API

## Base URL
```
/api/admin/notification-templates
```

## Authentication
Requires `Authorization: Bearer <token>` header with Super Admin privileges.

---

## 1. List Templates

Get all notification templates.

**Endpoint:** `GET /admin/notification-templates`

### Response (200)
```json
{
  "success": true,
  "message": "Lấy danh sách mẫu thông báo thành công",
  "data": {
    "templates": [
      {
        "id": 1,
        "name": "Thông báo giải đấu mới",
        "title": "Giải đấu Picki Cup 2026",
        "content": "Giải đấu sẽ bắt đầu vào ngày mai",
        "action_type": "TOURNAMENT",
        "action_type_label": "Trận đấu",
        "action_id": 123,
        "recipient_type": "ALL",
        "recipient_type_label": "Tất cả người dùng",
        "recipient_config": {},
        "created_by": 1,
        "creator_name": "Admin Name",
        "created_at": "2026-08-24T10:00:00Z",
        "updated_at": "2026-08-24T10:00:00Z"
      }
    ]
  }
}
```

---

## 2. Get Single Template

Get a specific template by ID.

**Endpoint:** `GET /admin/notification-templates/{id}`

### Response (200)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Thông báo giải đấu mới",
    "title": "Giải đấu Picki Cup 2026",
    "content": "Giải đấu sẽ bắt đầu vào ngày mai",
    "action_type": "TOURNAMENT",
    "action_type_label": "Trận đấu",
    "action_id": 123,
    "recipient_type": "ALL",
    "recipient_type_label": "Tất cả người dùng",
    "recipient_config": {},
    "created_by": 1,
    "creator_name": "Admin Name",
    "created_at": "2026-08-24T10:00:00Z",
    "updated_at": "2026-08-24T10:00:00Z"
  }
}
```

### Response (404)
```json
{
  "success": false,
  "message": "Mẫu thông báo không tồn tại"
}
```

---

## 3. Create Template

Create a new notification template.

**Endpoint:** `POST /admin/notification-templates`

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Template name (max 255 chars) |
| `title` | string | Yes | Notification title (max 50 chars) |
| `content` | string | Yes | Notification content (max 150 chars) |
| `action_type` | string | No | Action type (see Action Types) |
| `action_id` | integer | No | Target ID for action |
| `recipient_type` | string | Yes | Recipient type (see Recipient Types) |
| `recipient_config` | object | No | Recipient configuration |

### Action Types

| Value | Description |
|-------|-------------|
| `NONE` | No action |
| `MATCH` | Quick match |
| `TOURNAMENT` | Tournament |
| `CLUB` | Club |

### Recipient Types

| Value | Description | Required Config |
|-------|-------------|-----------------|
| `ALL` | All users | None |
| `CLUB` | By club | `{ "club_id": <id> }` |
| `ACTIVITY` | By activity level | `{ "level": "HOT|WARM|COLD" }` |
| `USERS` | By user list | `{ "user_ids": [1, 2, 3] }` (max 1000) |

### Example Request
```json
{
  "name": "Thông báo giải đấu mới",
  "title": "Giải đấu Picki Cup 2026",
  "content": "Giải đấu sẽ bắt đầu vào ngày mai. Đăng ký ngay!",
  "action_type": "TOURNAMENT",
  "action_id": 123,
  "recipient_type": "ALL",
  "recipient_config": {}
}
```

### Response (201)
```json
{
  "success": true,
  "message": "Lưu mẫu thông báo thành công",
  "data": {
    "id": 1,
    "name": "Thông báo giải đấu mới",
    ...
  }
}
```

### Validation Errors (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["Vui lòng nhập tên mẫu"],
    "title": ["Tiêu đề không được vượt quá 50 ký tự"]
  }
}
```

---

## 4. Update Template

Update an existing template.

**Endpoint:** `POST /admin/notification-templates/{id}`

### Request Body
Same as Create

### Response (200)
```json
{
  "success": true,
  "message": "Cập nhật mẫu thông báo thành công",
  "data": { ... }
}
```

---

## 5. Delete Template

Delete a notification template.

**Endpoint:** `DELETE /admin/notification-templates/{id}`

### Response (200)
```json
{
  "success": true,
  "message": "Xoá mẫu thông báo thành công"
}
```

---

## Error Responses

| Status | Description |
|--------|-------------|
| 401 | Unauthenticated |
| 403 | Forbidden (not super admin) |
| 404 | Template not found |
| 422 | Validation failed |

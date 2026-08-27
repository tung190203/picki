# Tournament Staff APIs

Quản lý thành viên ban tổ chức (Admin / BTC / Trọng tài) trong **Giải đấu lớn** (Tournament).

---

## Role Constants

| Value | Constant | Text | Quyền hạn |
|-------|----------|------|-----------|
| `1` | `ROLE_ORGANIZER` | Organizer / Admin | Gán/xoá mọi role |
| `2` | `ROLE_STAFF` | Staff / BTC | Gán/xoá được Trọng tài |
| `3` | `ROLE_REFEREE` | Referee / Trọng tài | Không có quyền gán/xoá |

---

## Permission Matrix

Quyền gán (`canAssignRole`) và thu hồi (`canRevokeRole`) dùng chung matrix:

| Caller ↓ \ Target → | Admin (1) | BTC (2) | Trọng tài (3) |
|---|---|---|---|
| **Admin** (organizer) | ✅ Gán / ✅ Xoá | ✅ Gán / ✅ Xoá | ✅ Gán / ✅ Xoá |
| **BTC** (staff) | ❌ | ❌ | ✅ Gán / ✅ Xoá |
| **Trọng tài** | ❌ | ❌ | ❌ |

> Không có guard "organizer cuối cùng" — 1 giải có thể có nhiều Admin.

---

## Endpoints

### 1. Thêm thành viên (gán role)

```
POST /api/tournament-staff/add/{tournamentId}
```

**Body:**

```json
{
  "user_id": 58,
  "role": 1,
  "court_id": null
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | int | ✅ | ID của user được thêm (từ bảng `users`) |
| `role` | int | ❌ (default: `1`) | Role được gán: `1`=Admin, `2`=BTC, `3`=Trọng tài |
| `court_id` | int | ❌ | Chỉ áp dụng khi `role=3`. Giới hạn scope trọng tài theo sân. `null` = được score mọi sân. |

**Permission:** Caller phải có quyền gán `role` tương ứng (xem matrix).

**Responses:**

```
201 Created
{
  "message": "Thêm người tổ chức thành công",
  "data": null
}

409 Conflict
{
  "message": "Người dùng đã là thành viên ban tổ chức của giải đấu"
}

403 Forbidden
{
  "message": "Bạn không có quyền thêm thành viên vào ban tổ chức"
}
```

---

### 2. Thêm trọng tài (legacy endpoint)

```
POST /api/tournament-staff/add-referee/{tournamentId}
```

**Body:**

```json
{
  "user_id": 58,
  "court_id": null
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | int | ✅ | ID của user (từ bảng `users`) |
| `court_id` | int | ❌ | Giới hạn scope theo sân. `null` = mọi sân. |

**Permission:** Caller phải có quyền gán Trọng tài (Admin hoặc BTC).

**Responses:**

```
201 Created
{
  "message": "Thêm trọng tài thành công",
  "data": null
}

409 Conflict
{
  "message": "Người dùng này đã là thành viên ban tổ chức của giải đấu"
}

403 Forbidden
{
  "message": "Bạn không có quyền thêm trọng tài"
}
```

> **Note:** Endpoint này giữ lại để backward-compat. Với logic mới, nên dùng endpoint **Thêm thành viên** (`POST /add/{tournamentId}`) với `role=3`.

---

### 3. Xoá thành viên (thu hồi role)

```
DELETE /api/tournament-staff/{tournamentId}
```

**Body:**

```json
{
  "tournament_staff_id": 42
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `tournament_staff_id` | int | ✅ | ID của bản ghi trong bảng `tournament_staff` (không phải `user_id`) |

**Permission:** Caller phải có quyền thu hồi role tương ứng (xem matrix).

**Responses:**

```
200 OK
{
  "message": "Xoá Organizer thành công",
  "data": null
}
hoặc
{
  "message": "Xoá trọng tài thành công",
  "data": null
}

404 Not Found
{
  "message": "Không tìm thấy thành viên ban tổ chức"
}

403 Forbidden
{
  "message": "Bạn không có quyền xoá thành viên với vai trò này"
}
```

---

## Cách lấy `tournament_staff_id`

`staff_id` trong request xoá là **ID bản ghi** `tournament_staff.id`, không phải `user_id`.

FE lấy từ danh sách staff đã load sẵn:

```js
// Danh sách staff từ API lấy kèo, mỗi item có:
// { id: 42, user_id: 58, role: 3, ... }

// Khi click "Xoá" → gọi:
// DELETE /api/tournament-staff/1061
// body: { tournament_staff_id: 42 }
```

---

## Check-in / Absent (có sẵn)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/tournament/{id}/tournament-staff/{staffId}/mark-check-in` | Điểm danh staff |
| `POST` | `/api/tournament/{id}/tournament-staff/{staffId}/mark-absent` | Đánh dấu vắng |
| `POST` | `/api/tournament/{id}/tournament-staff/mark-check-in-all` | Điểm danh tất cả |

---

## Các helper methods trong Tournament model

```php
$tournament->hasOrganizer($userId)        // true nếu user là Admin
$tournament->hasBtc($userId)             // true nếu user là BTC
$tournament->hasReferee($userId)         // true nếu user là Trọng tài
$tournament->hasAdminOrBtc($userId)      // true nếu là Admin hoặc BTC
$tournament->hasScoringPermission($userId) // true nếu có quyền nhập điểm
```

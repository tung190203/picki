# Mini-Tournament Staff APIs

Quản lý thành viên ban tổ chức (Admin / BTC / Trọng tài) trong **Kèo đấu** (Mini-Tournament).

---

## Role Constants

| Value | Constant | Text | Quyền hạn |
|-------|----------|------|-----------|
| `1` | `ROLE_ADMIN` | admin | Gán/xoá mọi role |
| `2` | `ROLE_STAFF` | staff | Gán/xoá được Trọng tài |
| `3` | `ROLE_REFEREE` | referee | Không có quyền gán/xoá |

> Alias: `ROLE_ORGANIZER = ROLE_ADMIN` (để backward-compat với code cũ).

---

## Permission Matrix

Quyền gán (`canAssignRole`) và thu hồi (`canRevokeRole`) dùng chung matrix:

| Caller ↓ \ Target → | Admin (1) | BTC (2) | Trọng tài (3) |
|---|---|---|---|
| **Admin** | ✅ Gán / ✅ Xoá | ✅ Gán / ✅ Xoá | ✅ Gán / ✅ Xoá |
| **BTC** | ❌ | ❌ | ✅ Gán / ✅ Xoá |
| **Trọng tài** | ❌ | ❌ | ❌ |

> Không có guard "organizer cuối cùng" — 1 kèo có thể có nhiều Admin.

---

## Endpoints

### 1. Thêm thành viên (gán role)

```
POST /api/mini-tournament-staff/add/{miniTournamentId}
```

**Body:**

```json
{
  "staff_id": 58,
  "role": 3
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `staff_id` | int | ✅ | ID của user được thêm (từ bảng `users`) |
| `role` | int | ✅ | Role được gán: `1`=Admin, `2`=BTC, `3`=Trọng tài |

**Permission:** Caller phải có quyền gán `role` tương ứng (xem matrix).

**Responses:**

```
201 Created
{
  "message": "Thêm referee thành công",
  "data": null
}

409 Conflict
{
  "message": "Người dùng đã là thành viên của kèo (mỗi user chỉ giữ 1 role)"
}

403 Forbidden
{
  "message": "Bạn không có quyền gán vai trò này cho kèo đấu"
}
```

---

### 2. Xoá thành viên (thu hồi role)

```
DELETE /api/mini-tournament-staff/{miniTournamentId}/{staffId}
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `miniTournamentId` | int | ID của kèo đấu |
| `staffId` | int | ID bản ghi trong bảng `mini_tournament_staff` (không phải `user_id`) |

**Permission:** Caller phải có quyền thu hồi role tương ứng (xem matrix).

**Responses:**

```
200 OK
{
  "message": "Xoá admin thành công",
  "data": null
}
hoặc
{
  "message": "Xoá referee thành công",
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

### 4. Chuyển đổi role (trong 1 call)

```
PATCH /api/mini-tournament-staff/update/{miniTournamentId}
```

Thay vì phải gọi revoke → add (2 lần), endpoint này chuyển role trong 1 transaction.

**Body:**

```json
{
  "staff_id": 58,
  "new_role": 2
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `staff_id` | int | ✅ | ID của user trong bảng `users` (không phải `mini_tournament_staff.id`) |
| `new_role` | int | ✅ | Role mới: `1`=Admin, `2`=BTC, `3`=Trọng tài |

**Permission:** Caller phải có quyền **đồng thời**:
- Revoke role cũ của thành viên đó
- Assign role mới cho thành viên đó

| Ví dụ chuyển | Caller cần quyền |
|---|---|
| Admin → BTC | Revoke Admin + Assign BTC |
| Admin → Trọng tài | Revoke Admin + Assign Trọng tài |
| BTC → Admin | Revoke BTC (Admin mới có) + Assign Admin |
| BTC → Trọng tài | Revoke BTC (Admin mới có) + Assign Trọng tài |
| Trọng tài → Admin | Revoke Trọng tài + Assign Admin |
| Trọng tài → BTC | Revoke Trọng tài + Assign BTC |

> **Note:** Thực tế chỉ Admin mới có quyền revoke Admin/BTC, nên chỉ Admin mới chuyển được BTC ↔ Admin. BTC chỉ chuyển được Trọng tài ↔ BTC.

**Responses:**

```
200 OK
{
  "message": "Chuyển từ admin sang staff thành công",
  "data": null
}

400 Bad Request
{
  "message": "Thành viên đã có vai trò này"
}

404 Not Found
{
  "message": "Thành viên không tồn tại trong kèo đấu"
}

403 Forbidden
{
  "message": "Bạn không có quyền thu hồi vai trò hiện tại của thành viên này"
}
hoặc
{
  "message": "Bạn không có quyền gán vai trò mới cho thành viên này"
}
```

---

## Cách lấy `staffId` (mini_tournament_staff.id)

`staffId` trong request xoá là **ID bản ghi** `mini_tournament_staff.id`, không phải `user_id`.

FE lấy từ danh sách staff đã load sẵn:

```js
// Danh sách staff từ API lấy kèo, mỗi item có:
// { id: 42, user_id: 58, role: 3, ... }

// Khi click "Xoá" → gọi:
// DELETE /api/mini-tournament-staff/1061/42
```

---

## So sánh với Tournament (Giải đấu lớn)

| | Mini-Tournament | Tournament |
|---|---|---|
| **Thêm** | `POST /mini-tournament-staff/add/{id}` | `POST /tournament-staff/add/{id}` |
| **Xoá** | `DELETE /mini-tournament-staff/{id}/{staffId}` | `DELETE /tournament-staff/{id}` |
| **Chuyển role** | `PATCH /mini-tournament-staff/update/{id}` | ❌ Chưa có |
| **Body xoá** | path param `staffId` | body `{ tournament_staff_id }` |
| **Staff ID field** | `staff_id` | `user_id` |
| **court_id** | ❌ Không có | ✅ Có (cho Trọng tài) |
| **Permission matrix** | Admin xoá mọi, BTC chỉ Trọng tài | Đồng bộ |

---

## Các helper methods trong MiniTournament model

```php
$mini->hasAdmin($userId)          // true nếu user là Admin
$mini->hasBtc($userId)            // true nếu user là BTC
$mini->hasReferee($userId)        // true nếu user là Trọng tài
$mini->isAdminOrBtc($userId)      // true nếu là Admin hoặc BTC
```

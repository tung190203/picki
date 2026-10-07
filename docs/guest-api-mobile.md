# API Guest — Mobile Integration

> Scope: tài liệu tối giản cho app mobile, focus vào flow mời/CRUD guest cho CLB & event.

Base URL: `https://<host>/api`
Auth: `Authorization: Bearer <token>` (Laravel Sanctum)

---

## 1. Khái niệm

| Thuật ngữ | Ý nghĩa |
|---|---|
| **Guest** | User ảo (`User.is_guest = true`) — không có tài khoản đăng nhập, do CLB hoặc organizer tạo để tham gia event. |
| **CLB guest** | Guest do CLB quản lý — lưu ở bảng `club_guest_profiles`. Có thể mời vào MỌI event (kể cả event CLB khác). |
| **Per-event guest** | Guest tạo inline khi thêm vào 1 event cụ thể — KHÔNG tạo `ClubGuestProfile`. |
| **estimated_level** | Trình độ ước tính (1.0–8.0). Guest dùng con số này thay cho VNDUPR score trên leaderboard/event. |

---

## 2. 2 nhánh tạo guest

| Nhánh | Trigger | Lưu CLB guest? | Lưu trình? |
|---|---|---|---|
| **A. Per-event** | POST event mà KHÔNG truyền `club_id` | Không | `estimated_level_min/max` lưu trên participant (kèo) hoặc `estimated_level` (giải) |
| **B. CLB guest** | POST event CÓ truyền `club_id` | Có (`club_guest_profiles`) | `estimated_level` lưu trên profile, snapshot xuống participant |

> Quy tắc chung: `club_id` truyền lên ⇒ tạo/lấy CLB guest. Không truyền ⇒ guest inline cho event đó.

---

## 3. CRUD CLB guest (B)

### 3.1. Danh sách CLB guest

```
GET /api/clubs/{clubId}/guests/profiles
```

**Query params:** `search` (optional, match tên user)

**Permission:** Admin/Manager/Secretary của CLB (`canManage()`).

**Response 200:**
```json
{
  "success": true,
  "message": "Lấy danh sách CLB guest thành công",
  "data": [
    {
      "id": 17,
      "club_id": 5,
      "estimated_level": 4.5,
      "user": {
        "id": 555,
        "full_name": "Anh Tuấn",
        "phone": "0987654321",
        "avatar_url": "https://.../guest-avatars/abc.jpg",
        "is_guest": true
      },
      "created_by": 33,
      "created_at": "2026-10-07T09:00:00Z"
    }
  ]
}
```

### 3.2. Tạo CLB guest

```
POST /api/clubs/{clubId}/guests/profiles
Content-Type: multipart/form-data   (khi có guest_avatar)
Content-Type: application/json      (khi không upload ảnh)
```

| Field | Type | Bắt buộc | Mô tả |
|---|---|---|---|
| `guest_name` | string | ✅ | Tên hiển thị |
| `guest_phone` | string | ❌ | SĐT (tùy chọn). Nếu trùng user thật → giữ user thật. Nếu trùng guest cũ → update tên. |
| `guest_avatar` | file | ❌ | jpeg/png/jpg/gif/svg/webp, max 5MB |
| `estimated_level` | float | ❌ | 1.0–8.0 |

**Response 201:** giống cấu trúc 1 phần tử trong §3.1.

**Logic:**
- Nếu có `guest_phone` và trùng user thật (`User.is_guest = false`) → KHÔNG set `is_guest`, KHÔNG tạo profile (cảnh báo ở FE).
- Nếu là guest cũ → update tên/avatar.
- Nếu chưa có → tạo mới `User.is_guest = true` + `ClubGuestProfile`.
- `updateOrCreate` trên `club_id+user_id`: luôn sync `estimated_level` mỗi lần gọi.

### 3.3. Sửa CLB guest

```
PUT /api/clubs/{clubId}/guests/profiles/{profileId}
```

| Field | Type | Mô tả |
|---|---|---|
| `guest_name` | string | Đổi tên user |
| `guest_phone` | string | Đổi SĐT user |
| `guest_avatar` | file | Upload avatar mới |
| `estimated_level` | float | 1.0–8.0 |

**Response 200:** giống cấu trúc 1 phần tử trong §3.1.

### 3.4. Xoá CLB guest (soft-delete)

```
DELETE /api/clubs/{clubId}/guests/profiles/{profileId}
```

**Lưu ý:** chỉ xoá `club_guest_profiles` (soft-delete). `User` giữ nguyên — user có thể thuộc CLB khác.

**Response 200:**
```json
{ "success": true, "message": "Đã xóa CLB guest khỏi CLB" }
```

---

## 4. Mời user thường vào event (mobile dùng chính)

> **Không có endpoint riêng cho guest.** Mobile dùng chung API invite user thường.
> Guest ở đây = user đã có sẵn (do CLB nào đó tạo, hoặc do BE tự sinh qua `POST .../guests` của từng event).

### 4.1. Mời vào kèo (mini-tournament)

```
POST /api/mini-participants/invite/{miniTournamentId}
```

**Body (application/json):**

| Field | Type | Bắt buộc | Mô tả |
|---|---|---|---|
| `user_ids` | int[] | ✅ ít nhất 1 | User muốn mời (cả user thật lẫn guest đều có `user_id`) |
| `is_invite_around` | int/bool | ❌ | `1` = bật cờ mời người xung quanh (yêu cầu tổ chức thành công ≥3 mini-tournament) |

**Ví dụ:**
```json
{
  "user_ids": [2294],
  "is_invite_around": 0
}
```

**Response 201/207:**
```json
{
  "success": true,
  "message": "Đã gửi lời mời tham gia kèo đấu cho 1 người chơi.",
  "data": {
    "invited": [/* MiniParticipantResource[] */],
    "failed": [{"user_id": 999, "reason": "Người chơi đã tham gia."}],
    "invited_count": 1,
    "failed_count": 0
  }
}
```

### 4.2. Mời vào giải (Tournament)

```
POST /api/participants/invite-user/{tournamentId}
```

Body giống §4.1, **không có** `is_invite_around`.

**Ví dụ:**
```json
{
  "user_ids": [2294]
}
```

**Response 201/207:** giống §4.1 (list invited/failed).

---

## 5. Mời nhiều — multi-invite (mobile dùng nâng cao)

> Chỉ dùng khi cần mời **hỗn hợp** user thật + CLB guest, hoặc tạo CLB guest mới inline.

### 5.1. Multi-invite kèo

```
POST /api/mini-participants/invite/{miniTournamentId}
```

```json
{
  "user_ids": [101, 102],
  "club_guest_profile_ids": [17, 18],
  "is_invite_around": 0
}
```

| Field | Type | Mô tả |
|---|---|---|
| `user_ids` | int[] | User thật muốn mời |
| `club_guest_profile_ids` | int[] | CLB guest có sẵn (resolve sang `user_id` qua `club_guest_profiles.user_id`) |
| `is_invite_around` | int/bool | Bật cờ "mời người xung quanh" |

**Lưu ý quan trọng:** Khi resolve `club_guest_profile_ids`, BE **snapshot `estimated_level` từ `ClubGuestProfile` xuống `mini_participants.estimated_level_min/max`** (nếu profile có). Nếu 1 user xuất hiện cả ở `user_ids` và `club_guest_profile_ids` thì `user_ids` thắng (không ghi đè `estimated_level`).

**Response 201/207:** giống §4.1.

### 5.2. Multi-invite giải

```
POST /api/participants/invite-user/{tournamentId}
```

Body giống §5.1, không có `is_invite_around`. `club_guest_profile_ids` snapshot xuống `participants.estimated_level`.

---

## 7. Error codes

| Status | Ý nghĩa |
|---|---|
| 401 | Chưa đăng nhập / token hết hạn |
| 403 | Không phải admin/manager/secretary của CLB, hoặc không phải organizer của event |
| 404 | clubId / eventId / profileId không tồn tại |
| 422 | Validation lỗi (thiếu field, sai range, v.v.) |
| 500 | Lỗi server |

Tất cả error response:
```json
{
  "success": false,
  "message": "Mô tả lỗi (tiếng Việt)"
}
```

---

# Club Follow API & Guests

Tính năng theo dõi CLB + theo dõi khách chơi kèo/giải.

## Phần 1 — Follow CLB

Tận dụng endpoint `follows` polymorphic có sẵn (`followable_type: club`) — không có route mới, chỉ thêm `'club'` vào map của `FollowController`.

### Flow tổng quan

- User gửi yêu cầu tham gia CLB → tự động follow CLB.
- User leave CLB → tự động unfollow CLB.
- User chưa tham gia có thể bấm theo dõi / bỏ theo dõi từ ClubDetail.
- Khi CLB tạo mini-tournament hoặc Tournament → gửi notification + push tới tất cả thành viên CLB + follower (không trùng thành viên).

### API

#### Theo dõi CLB

```
POST /api/follows/store
Body: { followable_type: "club", followable_id: <club_id> }
```

Trả về object `Follow` vừa tạo. Idempotent (`firstOrCreate`) — nếu đã follow thì trả về record cũ, status vẫn 200.

#### Bỏ theo dõi

```
POST /api/follows/delete
Body: { followable_type: "club", followable_id: <club_id> }
```

Xoá bản ghi follow. Nếu không tồn tại → 400.

#### Lấy danh sách follow (lọc theo `Club`)

```
GET /api/follows/index
```

Response gom nhóm theo loại: `{ club: [...], user: [...], ... }`. Từng entry trả `FollowResource` (id, type, data = ClubResource của club đó).

## Phần 2 — Guests (Khách chơi kèo/giải)

User tham gia kèo/giải của CLB nhưng chưa phải thành viên sẽ được track vào bảng `club_guests`.

### Cấu trúc bảng `club_guests`

```
club_guests
  club_id          FK → clubs
  user_id          FK → users
  play_count       INT
  first_played_at  TIMESTAMP
  last_played_at   TIMESTAMP
  is_invited       BOOL (admin đã gửi lời mời tham gia CLB)
  PK (club_id, user_id)
```

### Khi nào cập nhật `club_guests`?

- `Tournament::status = finished` (sau khi giải kết thúc)
- `MiniTournament::status = STATUS_CLOSED` (sau khi kèo đóng)

Observer `TournamentObserver` + `MiniTournamentObserver` gọi `ClubGuestService::upsertFromEvent()` → tăng `play_count` cho user đã tồn tại, tạo mới cho user lần đầu.

### Phân nhóm khách

| Nhóm | Điều kiện |
|---|---|
| `normal` | Chưa tham gia event trong 30 ngày gần nhất (hoặc chưa từng tham gia gần đây) |
| `potential` | Đã tham gia event trong 30 ngày gần nhất (khách quay lại) |

### API

#### Danh sách khách

```
GET /api/clubs/{clubId}/guests
```

Response:
```json
{
  "data": {
    "normal": [
      {
        "user_id": 5,
        "play_count": 3,
        "days_since_last_play": 45,
        "is_invited": false,
        "last_played_at": "2026-08-22T10:30:00Z",
        "user": {
          "id": 5,
          "full_name": "Nguyễn Văn A",
          "avatar_url": "https://...",
          "sports": [{ "sport_id": 1, "sport_name": "Pickleball", "scores": { ... } }]
        }
      }
    ],
    "potential": [...],
    "counts": { "normal": 12, "potential": 4, "total": 16 }
  }
}
```

Quyền: chỉ admin/manager/secretary (xem `Club::canManage()`).

#### Mời khách vào CLB

```
POST /api/clubs/{clubId}/guests/invite
Body: { "user_id": 5 }
```

Hành vi:
- Đánh dấu `club_guests.is_invited = true`
- Gọi lại `ClubMemberManagementService::inviteMember()` → user nhận notification + push như lời mời bình thường
- Trả 400 nếu user đã là thành viên

Response:
```json
{ "data": { "is_invited": true }, "message": "Đã gửi lời mời tham gia CLB" }
```

#### Xoá khách

```
DELETE /api/clubs/{clubId}/guests/{userId}
```

Xoá bản ghi `club_guests` (chỉ xoá lịch sử khách, không kick khỏi event đã tham gia).

Quyền: chỉ admin/manager/secretary.

## Phần 3 — Field mới trong `GET /api/clubs/{id}` (ClubDetailResource)

| Field | Type | Mô tả | Scope |
|---|---|---|---|
| `is_following` | bool | User hiện tại có đang follow CLB không | Authenticated |
| `followers_count_excluding_members` | int | Số user theo dõi CLB mà KHÔNG nằm trong `club_members` (joined + active) | Authenticated |
| `mini_tournaments_today` | int | Số kèo + giải của CLB diễn ra hôm nay | Admin/Manager/Secretary |
| `unpaid_members_count` | int | Tổng lượt chưa thanh toán (fund + tournament + mini) | Admin/Manager/Secretary |
| `returning_guests_percent` | int | % user tham gia event trong 30 ngày gần nhất đã từng chơi CLB trước đó | Admin/Manager/Secretary |
| `guests_count` | int | Tổng khách chưa tham gia CLB (từ `club_guests`) | Admin/Manager/Secretary |

`is_following` và `followers_count_excluding_members` luôn có mặt (khi authenticated).
4 stats còn lại (`mini_tournaments_today`, `unpaid_members_count`, `returning_guests_percent`, `guests_count`) chỉ xuất hiện khi user là admin/manager/secretary (theo `Club::canManage()`).

## Phần 4 — Notification khi CLB tạo tournament / mini-tournament

Khi CLB tạo, hệ thống tự gửi tới tất cả member (joined+active) + follower (không trùng member). Hai kênh:

**1. Database notification** (`Laravel notifications`, hiển thị qua `GET /api/user-notifications/index`):
- `type`: `CLUB_MINI_TOURNAMENT_CREATED` hoặc `CLUB_TOURNAMENT_CREATED`
- `club_id`, `club_name`
- `tournament_id`, `tournament_type` (`mini_tournament` | `tournament`)
- `title`: `CLB {name} vừa tạo kèo/giải đấu mới`
- `message`: tên tournament

**2. FCM push** (qua `SendPushJob`, payload `data`):
```
{ "type": "...", "club_id": "...", "tournament_id": "...", "tournament_type": "..." }
```

App dựa vào `type` để deep-link vào detail tương ứng (`/tournament-detail/{id}` hoặc `/mini-tournament-detail/{id}`).

## Phần 5 — Lưu ý cho Mobile

### Follow

- `is_following` thay đổi khi user gửi/duyệt join request hoặc leave CLB — gọi lại `GET /clubs/{id}` sau mỗi action để sync state.
- Nút "Theo dõi" chỉ hiển thị cho user chưa tham gia CLB (`is_member = false`); user đã là member thì auto-follow, không cần thao tác.
- Tự động unfollow khi leave: app không cần gọi riêng `unfollowClub` trong flow leave.
- Mini-tournament và Tournament dùng chung 1 notification class; phân biệt bằng field `tournament_type`.

### Guests

- `club_guests` chỉ lưu user có `user_id` (đã đăng ký). Guest không tài khoản vẫn nằm trong `participants`/`mini_participants` và không hiển thị trong API guests.
- Sau khi gọi `POST /guests/invite`, cập nhật UI ngay: set `is_invited = true` cho guest đó.
- `DELETE /guests/{userId}` chỉ xoá lịch sử khách; lần sau tham gia event sẽ tự tạo lại bản ghi.
- 4 stats chỉ render khi user là admin/manager/secretary. User thường và guest sẽ không thấy các field này trong response.

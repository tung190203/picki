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

## Phần 6 — Sân nhà (`club_competition_locations`)

CLB chọn nhiều `competition_location` làm sân nhà. Lưu ở bảng pivot `club_competition_locations` với cột `position`. Hai field meta `distance_km` và `events_hosted_count` **do BE tự tính khi GET**, không nhập tay và không lưu lại. Hiển thị ở tab "Giới thiệu" của ClubDetail cho mọi viewer.

### Cấu trúc bảng `club_competition_locations`

```
club_competition_locations
  id                       PK
  club_id                  FK → clubs (cascade)
  competition_location_id  FK → competition_locations (cascade)
  position                 UNSIGNED INT — thứ tự ưu tiên hiển thị
  distance_km              DECIMAL(6,2) NULL — (legacy, không còn dùng — BE tự tính)
  events_hosted_count      UNSIGNED INT — (legacy, không còn dùng — BE tự đếm)
  created_at, updated_at
  UNIQUE (club_id, competition_location_id)
  INDEX (club_id, position)
```

> `distance_km` và `events_hosted_count` trong DB giữ lại để tương thích ngược, nhưng BE **không ghi** giá trị mới. Khi trả response, BE luôn tự tính đè.

### Quy tắc tự tính

- **`distance_km`**: Haversine từ anchor point tới `competition_locations.latitude/longitude`.
  - Anchor ưu tiên: `club.latitude/longitude` (nếu có).
  - Fallback: header request `X-User-Lat` / `X-User-Lng` (FE lấy từ `navigator.geolocation`).
  - Trả `null` nếu cả 2 đều thiếu toạ độ.
- **`events_hosted_count`**: Tổng số event đã finished của CLB tại sân đó:
  - `mini_tournaments` có `club_id = X` + `competition_location_id = Y` + `status = STATUS_CLOSED (3)`.
  - `tournaments` có `club_id = X` + `competition_location_id = Y` + `status = CLOSED (3)`.
  - Đếm lúc GET (không cache).

### API

#### Lấy danh sách sân nhà

```
GET /api/clubs/{clubId}/home-courts
Headers (optional):
  X-User-Lat: 10.79
  X-User-Lng: 106.67
```

Public — ai cũng xem được. Response:

```json
{
  "data": [
    {
      "id": 12,
      "competition_location_id": 7,
      "name": "Sân Pickleball Q7",
      "address": "12 Nguyễn Văn Trỗi, Q.Phú Nhuận",
      "latitude": 10.7995,
      "longitude": 106.6789,
      "position": 0,
      "distance_km": 1.20,
      "events_hosted_count": 8
    }
  ],
  "message": "Lấy danh sách sân nhà thành công"
}
```

- Sắp xếp theo `position` ASC, sau đó `id` ASC.
- `distance_km = null` nếu thiếu anchor và thiếu header toạ độ user.

#### Cập nhật danh sách sân nhà (sync toàn bộ)

```
POST /api/clubs/{clubId}/home-courts
Content-Type: application/json

{
  "locations": [
    { "competition_location_id": 7, "position": 0 },
    { "competition_location_id": 9, "position": 1 }
  ]
}
```

- `competition_location_id` required, phải tồn tại trong `competition_locations`.
- `position` optional (mặc định = index trong mảng).
- `distance_km` / `events_hosted_count` (nếu gửi) bị bỏ qua — BE tự tính lúc GET.
- Tối đa 10 sân mỗi CLB.
- Hành vi: xoá hết dòng pivot cũ của CLB rồi insert lại (idempotent). Trong 1 transaction.
- Quyền: chỉ admin/manager/secretary (`Club::canManage()`). User khác → 403.

Response: trả về danh sách sân nhà mới (cùng format GET, đã có `distance_km` / `events_hosted_count` tự tính).

#### Patch 1 dòng pivot

```
PUT /api/clubs/{clubId}/home-courts/{homeCourtId}
Content-Type: application/json

{
  "position": 2
}
```

- `position` optional. `distance_km` / `events_hosted_count` bị bỏ qua nếu gửi.
- Quyền: `canManage()`. Trả 404 nếu `homeCourtId` không thuộc CLB.

#### Xoá 1 sân nhà

```
DELETE /api/clubs/{clubId}/home-courts/{homeCourtId}
```

Quyền: `canManage()`. Trả 404 nếu không tồn tại.

### Lưu ý

- `distance_km` và `events_hosted_count` trong pivot DB **chỉ là column tương thích ngược** — luôn rỗng/0 đối với record mới. Khi trả response, BE tính lại và ghi đè vào field top-level.
- FE cần gửi header `X-User-Lat` / `X-User-Lng` để BE dùng làm anchor fallback khi CLB chưa có toạ độ.
- Frontend modal thêm/sửa sân nhà **không** có input cho 2 field này nữa.

## Phần 7 — Lịch sinh hoạt định kỳ (`club_recurring_schedules`)

CLB khai báo nhiều khung giờ sinh hoạt trong tuần (thứ + giờ bắt đầu + giờ kết thúc + ghi chú). Lịch gắn với CLB, áp dụng cho mọi sân nhà.

### Cấu trúc bảng `club_recurring_schedules`

```
club_recurring_schedules
  id            PK
  club_id       FK → clubs (cascade)
  day_of_week   TINYINT — 0 = CN, 1 = T2, ..., 6 = T7
  start_time    TIME
  end_time      TIME
  note          VARCHAR(255) NULL
  position      UNSIGNED INT
  created_at, updated_at
  INDEX (club_id, day_of_week, position)
```

### API

#### Lấy danh sách lịch sinh hoạt

```
GET /api/clubs/{clubId}/recurring-schedules
```

Public. Sắp xếp theo `day_of_week`, `position`, `start_time`. Response:

```json
{
  "data": [
    {
      "id": 3,
      "day_of_week": 1,
      "start_time": "18:00:00",
      "end_time": "21:00:00",
      "note": "Sân chính",
      "position": 0
    }
  ],
  "message": "Lấy lịch sinh hoạt thành công"
}
```

#### Thêm 1 dòng lịch

```
POST /api/clubs/{clubId}/recurring-schedules
Content-Type: application/json

{
  "day_of_week": 1,
  "start_time": "18:00",
  "end_time": "21:00",
  "note": "Sân chính",
  "position": 0
}
```

- `day_of_week`: integer 0-6 (0 = Chủ nhật).
- `start_time`, `end_time`: định dạng `H:i` hoặc `H:i:s`.
- `end_time` phải sau `start_time` (`after:start_time`).
- `note` optional, tối đa 255 ký tự.
- `position` optional (mặc định 0).
- Quyền: `canManage()`. Trả 201 + object vừa tạo.

#### Sửa 1 dòng lịch

```
PUT /api/clubs/{clubId}/recurring-schedules/{scheduleId}
```

Body giống POST, các field optional. Quyền: `canManage()`.

#### Xoá 1 dòng lịch

```
DELETE /api/clubs/{clubId}/recurring-schedules/{scheduleId}
```

Quyền: `canManage()`. Trả 404 nếu không tồn tại.

### Lưu ý

- `day_of_week` trả về integer 0-6, FE tự map sang "Chủ nhật", "Thứ 2", ...
- `start_time` / `end_time` trả về string `H:i:s` (theo MySQL TIME). FE cắt `:00` nếu muốn hiển thị gọn.

## Phần 8 — Trạng thái tuyển thành viên (`clubs.recruitment_status`)

Cột enum trên `clubs`: `'open' | 'closed'`, mặc định `'closed'`. Điều khiển nút "Tham gia" / "Theo dõi" ở frontend:

| Status | Badge hiển thị | Nút tham gia | Nút theo dõi |
|---|---|---|---|
| `open` | "Đang tuyển thành viên" | Hiện → gửi yêu cầu, Admin/BTC duyệt trong tab Thành viên | Hiện |
| `closed` | Không | Ẩn — chỉ hiện nút Theo dõi | Hiện |

Nút "Theo dõi" LUÔN hiện ở cả 2 trạng thái.

### Cấu trúc cột

```
clubs.recruitment_status  ENUM('open', 'closed') DEFAULT 'closed'  -- after is_banned
```

### API

#### Cập nhật recruitment_status (chỉ super_admin)

```
POST /api/admin/clubs/{clubId}/recruitment-status
Content-Type: application/json

{ "recruitment_status": "open" }
```

- Middleware: `auth:api` + `super_admin`. User thường → 403.
- Validate: `recruitment_status` required, in `['open', 'closed']`.
- Response:

```json
{
  "data": { "recruitment_status": "open" },
  "message": "Đã cập nhật trạng thái tuyển thành viên"
}
```

### Lưu ý

- `recruitment_status` KHÔNG nằm trong payload của `PUT /api/clubs/{clubId}` (chỉ super_admin đổi được, qua endpoint admin riêng).
- Field này xuất hiện trong response `GET /api/clubs/{id}` cho MỌI viewer (cả user chưa đăng nhập), ở top-level cùng `is_public`, `is_verified`, `is_banned`.

## Phần 9 — Field mới trong `GET /api/clubs/{id}` (ClubDetailResource) — tiếp theo

Bổ sung vào bảng Phần 3:

| Field | Type | Mô tả | Scope |
|---|---|---|---|
| `recruitment_status` | string (`open`/`closed`) | Trạng thái tuyển thành viên của CLB | Public (ai cũng thấy) |
| `home_courts` | array | Danh sách sân nhà (id, competition_location_id, name, address, lat/lng, position, distance_km, events_hosted_count) | Public |
| `recurring_schedules` | array | Lịch sinh hoạt định kỳ (id, day_of_week, start_time, end_time, note, position) | Public |

3 field này LUÔN có mặt trong response (không cần `when` điều kiện). Eager load từ `ClubController::show()` qua 2 quan hệ mới: `homeCourts` (belongsToMany) và `recurringSchedules` (hasMany).

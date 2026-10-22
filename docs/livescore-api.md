# Livescore API — Màn chiếu tỉ số (BTC)

Tài liệu cho API và kênh realtime phục vụ **màn Livescore** (màn chiếu tỉ số lên TV/máy chiếu tại giải).

Phân biệt với **Live Score API** (public, dành cho khán giả xem trận đấu đang diễn ra) — `GET /api/live-score/{type}/{matchId}`, không cần đăng nhập.

| API | Auth | Mục đích |
|---|---|---|
| `GET /api/livescore/tournaments` | Bearer | Danh sách giải đang diễn ra mà user tham gia / làm BTC |
| `GET /api/livescore/tournaments/{id}/matches` | Bearer | Toàn bộ trận của 1 giải |

**Base URL:** `/api`

### Response wrapper

API này dùng `ResponseHelper::success()` / `ResponseHelper::error()`:
- Thành công: `{ "status": true, "message": "...", "data": [...], "meta": null }` — `data` là **mảng** (list giải hoặc list trận), trả thẳng không bọc key
- Lỗi: `{ "status": false, "message": "...", "errors": {...} }`

Khác với `ResponseHelper::single()` / `paginated()` vốn dùng key `success` (không phải `status`).

**Ngoại lệ — lỗi 401:** khi thiếu token hoặc token hết hạn, middleware `auth:api` chặn trước khi vào controller, nên trả về format mặc định của Laravel (không phải `ResponseHelper`):

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

---

## GET `/api/livescore/tournaments` — Danh sách giải đang diễn ra

**Auth:** Required (Bearer Token)
**Method:** `GET`
**Query:** không có tham số

Trả về các giải mà user **tham gia** (đăng ký làm VĐV — bảng `participants`) **hoặc** thuộc BTC / trọng tài (bảng `tournament_staff`) — giống đúng logic `upcoming_tournaments` ở API `/api/home`. User không liên quan gì tới giải sẽ **không thấy** giải đó.

Chỉ trả về giải **đang diễn ra**: đã bắt đầu (`start_date <= hôm nay`) và chưa kết thúc (`end_date` rỗng hoặc `end_date >= hôm nay`).

`data` trả thẳng **list giải** (mảng), không bọc trong key `tournaments`. Mỗi giải **không** kèm danh sách trận — dùng API thứ 2 để lấy trận.

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Success",
  "data": [
    {
      "id": 294,
      "name": "Giải Pickleball Thu Đông 2026",
      "poster_url": "https://picki.vn/storage/posters/2026/10/giai-thu-dong.jpg",
      "description": "Giải đấu thường niên quy mô 64 đội",
      "location_name": "Cung Thể thao Rạch Miễu",
      "start_date": "2026-10-15T00:00:00Z",
      "end_date": "2026-10-22T00:00:00Z"
    }
  ],
  "meta": null
}
```

#### Field giải

| Field | Kiểu | Mô tả |
|---|---|---|
| `id` | int | ID giải |
| `name` | string | Tên giải |
| `poster_url` | string \| null | Ảnh poster. Nếu DB lưu đường dẫn tương đối, API tự ghép thành URL tuyệt đối |
| `description` | string \| null | Mô tả giải |
| `location_name` | string \| null | Tên địa điểm thi đấu |
| `start_date` | string \| null | Ngày bắt đầu (ISO-8601) |
| `end_date` | string \| null | Ngày kết thúc (ISO-8601) |

---

## GET `/api/livescore/tournaments/{id}/matches` — Toàn bộ trận của 1 giải

**Auth:** Required (Bearer Token)
**Method:** `GET`
**Path param:** `id` — ID giải (lấy từ API `/api/livescore/tournaments`)

Trả về **toàn bộ trận** của giải (không chỉ trận hôm nay), đã bốc sẵn field cho màn chiếu. Bỏ qua trận `bye` và trận chưa xác định đủ 2 đội.

`data` trả thẳng **list trận** (mảng), không bọc trong key `matches`.

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Success",
  "data": [
    {
      "match_id": 12673,
      "round": "Bán kết",
      "scheduled_at": "2026-10-21T09:00:00Z",
      "live_status": "playing",
      "status": "pending",
      "version": 42,
      "is_going_on": true,
      "is_finished": false,
      "team1_score": 17,
      "team2_score": 15,
      "team1": { "id": 12, "name": "Đội Đỏ" },
      "team2": { "id": 18, "name": "Đội Xanh" }
    }
  ],
  "meta": null
}
```

#### Field trận

| Field | Kiểu | Mô tả |
|---|---|---|
| `match_id` | int | ID trận — dùng để mở màn chiếu `/live-score/tournament/{match_id}` |
| `round` | string \| null | Vòng đấu |
| `scheduled_at` | string \| null | Giờ bắt đầu (ISO-8601) |
| `live_status` | string | Trạng thái live: `waiting` / `playing` / `timeout` / `between_sets` / `finish` / `cancelled` |
| `status` | string | Trạng thái trận: `pending` / `completed` / `disputed` |
| `version` | int | Phiên bản trận (`match_version`) — FE dùng để bỏ qua event realtime đến lệch thứ tự |
| `is_going_on` | bool | Đã bắt đầu **và** chưa xác nhận kết quả — dùng để badge "LIVE" |
| `is_finished` | bool | `status === 'completed'` hoặc `live_status === 'finish'` |
| `team1_score` | int | Điểm set hiện tại của đội 1 |
| `team2_score` | int | Điểm set hiện tại của đội 2 |
| `team1` | object | `{ id, name }` |
| `team2` | object | `{ id, name }` |

#### Lỗi

| Code | Message | Ý nghĩa |
|---|---|---|
| 404 | `Không tìm thấy giải đấu` | ID giải không tồn tại |
| 403 | `Bạn không có quyền xem trận của giải này` | User không tham gia và không phải BTC / trọng tài của giải đó |

---

## Realtime — Socket (Pusher / Laravel Echo)

Sau khi gọi `GET /api/livescore/tournaments`, FE subscribe kênh `tournament.{id}` cho **từng giải** trong `data` để nhận cập nhật tỉ số **tức thì** mà không cần poll lại API.

| Thuộc tính | Giá trị |
|---|---|
| Broadcaster | Pusher (qua `laravel-echo` + `pusher-js`) |
| Kênh | `tournament.{tournament_id}` — **public channel** (`Echo.channel`), không cần auth cho kênh này |
| Tên sự kiện (server) | `tournament.score_updated` |
| Tên sự kiện (client) | `.tournament.score_updated` (có dấu `.` đầu vì event dùng `broadcastAs`, không có namespace) |
| Chế độ phát | `ShouldBroadcastNow` — phát đồng bộ, không qua queue |

Ví dụ subscribe:

```js
const channel = window.Echo.channel(`tournament.${tournamentId}`);

channel.listen('.tournament.score_updated', (payload) => {
  // payload.matches: snapshot tỉ số hiện tại của các trận trong giải
});

// Khi rời màn:
channel.stopListening('.tournament.score_updated');
```

**Khi nào server phát event:** mỗi lần tỉ số/trạng thái trận của giải thay đổi (bắt đầu trận, cộng/trừ điểm, đổi set, kết thúc...), phát từ `MatchScoreService` và `MatchesController`. Mỗi lần phát đều gửi **snapshot toàn giải**, không phải delta, nên FE chỉ cần ghi đè theo `match_id`.

**Payload của sự kiện `tournament.score_updated`:**

```json
{
  "tournament_id": 294,
  "trigger_match_id": 12673,
  "version": 42,
  "matches": [
    {
      "match_id": 12673,
      "current_set": 3,
      "live_status": "playing",
      "team1_score": 17,
      "team2_score": 15,
      "version": 42,
      "is_going_on": true
    }
  ]
}
```

| Field | Kiểu | Mô tả |
|---|---|---|
| `tournament_id` | int | ID giải — trùng với `{id}` của kênh |
| `trigger_match_id` | int | Trận vừa thay đổi và gây ra event này |
| `version` | int | `match_version` của `trigger_match_id` |
| `matches` | array | Snapshot các trận của giải: mọi trận đã xác định đủ 2 đội, **không** gồm trận bye |
| `matches[].match_id` | int | ID trận — khớp với `match_id` trong response `/matches` |
| `matches[].current_set` | int | Set hiện tại (mặc định `1`) |
| `matches[].live_status` | string | `waiting` / `playing` / `finish` |
| `matches[].team1_score` | int | Điểm set hiện tại của đội 1 (đội nhà) |
| `matches[].team2_score` | int | Điểm set hiện tại của đội 2 (đội khách) |
| `matches[].version` | int | `match_version` của trận đó — dùng để bỏ qua dữ liệu cũ nếu event đến lệch thứ tự |
| `matches[].is_going_on` | bool | Cùng định nghĩa với field `is_going_on` ở trên: đã bắt đầu **và** chưa xác nhận kết quả |

**Cách FE áp dụng payload:** với mỗi phần tử trong `matches`, tìm trận trùng `match_id` trong list trận của giải (đã tải từ `/api/livescore/tournaments/{id}/matches`) và ghi đè `team1_score`, `team2_score`, `live_status`, `is_going_on`. Trận nào không có trong list thì bỏ qua.

**Lưu ý:**
- Payload **không có** `status` và `is_finished`. Hai field này chỉ cập nhật khi gọi lại API lấy trận.
- `team1_score` / `team2_score` ở payload và ở response API lấy trận cùng nghĩa (điểm set hiện tại), nên có thể trộn dữ liệu 2 nguồn.
- Cần `window.Echo` đã khởi tạo (xem `resources/js/bootstrap.js`; cấu hình qua biến `VITE_PUSHER_*`). Nếu chưa có Echo, FE bỏ qua realtime và chỉ hiển thị dữ liệu lúc gọi API.
- Hiện **chưa có** cơ chế poll dự phòng khi Pusher rớt kết nối. Muốn lấy lại dữ liệu mới nhất, gọi lại API lấy trận của giải tương ứng.

### Gợi ý triển khai phía FE (màn `/tools/livescore`)

- Subscribe kênh `tournament.{id}` **chỉ khi người dùng đang xem giải đó** → tránh nhận nhiễm.
- So sánh `payload.matches[].version` với `version` của trận trong list: nếu `payload.version < list.version` thì bỏ qua event.
- Payload **không có** `status` / `is_finished`. Khi nhận event nên gọi lại `GET /api/livescore/tournaments/{id}/matches` (debounce ~1.5s) để đồng bộ 2 field này.
- Trạng thái kết nối Pusher: `Echo.connector.pusher.connection.bind('state_change', ...)` → hiển thị badge "Realtime" / "Mất kết nối" cho BTC.

---

## Response — 200 OK (không có quyền nào)

Nếu user đăng nhập nhưng không tham gia / không phải BTC / trọng tài của giải nào, API `/api/livescore/tournaments` trả về mảng rỗng (không phải lỗi):

```json
{
  "status": true,
  "message": "Success",
  "data": [],
  "meta": null
}
```

---

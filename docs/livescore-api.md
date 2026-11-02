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

Trả về **toàn bộ trận** của giải (không chỉ trận hôm nay), đã bốc sẵn field cho màn chiếu. Bỏ qua trận `bye`. **Trận vòng trong chưa xác định đủ 2 đội vẫn được trả** với tên đội là `TBD` (xem phần *Trận chưa rõ đội* bên dưới).

Trận được sắp xếp theo `round` → `scheduled_at` → `id`.

`data` trả thẳng **list trận** (mảng), không bọc trong key `matches`.

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Success",
  "data": [
    {
      "match_id": 12670,
      "round": "Vòng bảng",
      "scheduled_at": "2026-10-21T07:30:00Z",
      "live_status": "waiting",
      "status": "pending",
      "version": 8,
      "is_going_on": false,
      "is_finished": false,
      "team1_score": 0,
      "team2_score": 0,
      "team1": {
        "id": 12,
        "name": "Đội Đỏ",
        "members": [
          {
            "id": 301,
            "full_name": "Nguyễn Văn A",
            "name": "Nguyễn Văn A",
            "avatar_url": "https://picki.vn/storage/avatars/a.jpg",
            "avatar": "https://picki.vn/storage/avatars/a.jpg",
            "is_guest": false
          },
          {
            "id": 512,
            "full_name": "Trần Thị B",
            "name": "Trần Thị B",
            "avatar_url": null,
            "avatar": null,
            "is_guest": true
          }
        ]
      },
      "team2": {
        "id": 18,
        "name": "Đội Xanh",
        "members": [
          {
            "id": 302,
            "full_name": "Lê Văn C",
            "name": "Lê Văn C",
            "avatar_url": "https://picki.vn/storage/avatars/c.jpg",
            "avatar": "https://picki.vn/storage/avatars/c.jpg",
            "is_guest": false
          }
        ]
      }
    },
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
      "team1": { "id": 12, "name": "Đội Đỏ", "members": [] },
      "team2": { "id": 18, "name": "Đội Xanh", "members": [] }
    },
    {
      "match_id": 12674,
      "round": "Chung kết",
      "scheduled_at": null,
      "live_status": "waiting",
      "status": "pending",
      "version": 1,
      "is_going_on": false,
      "is_finished": false,
      "team1_score": 0,
      "team2_score": 0,
      "team1": { "id": null, "name": "TBD" },
      "team2": { "id": null, "name": "TBD" }
    }
  ],
  "meta": null
}
```

#### Field trận

| Field | Kiểu | Mô tả |
|---|---|---|
| `match_id` | int | ID trận — dùng để mở màn chiếu `/live-score/tournament/{match_id}` |
| `round` | string \| null | **Tên** vòng đấu: `Vòng bảng` / `Vòng 1/32` / `Vòng 1/16` / `Vòng 1/8` / `Tứ kết` / `Bán kết` / `Chung kết` / `Tranh hạng Ba` (`null` nếu trận không thuộc vòng nào) |
| `scheduled_at` | string \| null | Giờ bắt đầu (ISO-8601) |
| `live_status` | string | Trạng thái live: `waiting` / `playing` / `timeout` / `between_sets` / `finish` / `cancelled` |
| `status` | string | Trạng thái trận: `pending` / `completed` / `disputed` |
| `version` | int | Phiên bản trận (`match_version`) — FE dùng để bỏ qua event realtime đến lệch thứ tự |
| `is_going_on` | bool | Đã bắt đầu **và** chưa xác nhận kết quả — dùng để badge "LIVE" |
| `is_finished` | bool | `status === 'completed'` hoặc `live_status === 'finish'` |
| `team1_score` | int | Điểm set hiện tại của đội 1 |
| `team2_score` | int | Điểm set hiện tại của đội 2 |
| `team1` | object | `{ id, name, members }` — `name` = `'TBD'` và `id` = `null` nếu đối thủ chưa xác định |
| `team2` | object | `{ id, name, members }` — `name` = `'TBD'` và `id` = `null` nếu đối thủ chưa xác định |

#### Field đội (`team1` / `team2`)

| Field | Kiểu | Mô tả |
|---|---|---|
| `id` | int \| null | ID đội — `null` nếu đối thủ của trận vòng trong chưa xác định |
| `name` | string | Tên đội — `'TBD'` nếu chưa xác định |
| `members` | array | Danh sách thành viên đội (user thật + guest) — mảng rỗng nếu đội chưa xác định |

Mỗi phần tử trong `members`:

| Field | Kiểu | Mô tả |
|---|---|---|
| `id` | int | ID user (user thật) hoặc ID participant (guest) |
| `full_name` | string | Tên hiển thị thành viên |
| `name` | string | Alias của `full_name` |
| `avatar_url` | string \| null | Link ảnh đại diện |
| `avatar` | string \| null | Alias của `avatar_url` |
| `is_guest` | bool | `true` nếu là VĐV ảo (guest) — không có bản ghi User riêng |

#### Trận chưa rõ đội (vòng trong)

Ở thể thức hỗn hợp / loại trực tiếp, các trận vòng trong được tạo sẵn trước khi biết đội nào đi tiếp. Những trận đó **vẫn xuất hiện** trong response với:

- `team1` / `team2` có `id: null` và `name: 'TBD'`;
- `round` vẫn là tên vòng đúng (ví dụ `Chung kết`);
- `team1_score` / `team2_score` = `0`.

Trận `bye` (`is_bye = 1`) thì không bao giờ được trả.

> **Realtime không phát sự kiện cho các trận này.** Payload `tournament.score_updated` chỉ gồm trận đã xác định đủ 2 đội. Tên đội của trận chỉ có **1 đội** (vòng trong chưa đủ) được cập nhật qua event riêng `tournament.advanced` — xem [mục dưới](#sự-kiện-tournamentadvanced--đội-vừa-điền-vào-vòng-trong). Nếu vẫn cần đồng bộ toàn bộ trạng thái (ngoài tỉ số và tên đội), gọi lại `GET /api/livescore/tournaments/{id}/matches`.

> **Màn chiếu không mở được với trận `TBD`** vì cần cả 2 đội — FE nên ẩn nút "Phát" cho các trận này.

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
| Tên sự kiện (server) | `tournament.score_updated`, `tournament.advanced` |
| Tên sự kiện (client) | `.tournament.score_updated`, `.tournament.advanced` (có dấu `.` đầu vì event dùng `broadcastAs`, không có namespace) |
| Chế độ phát | `ShouldBroadcastNow` — phát đồng bộ, không qua queue |

Ví dụ subscribe:

```js
const channel = window.Echo.channel(`tournament.${tournamentId}`);

channel.listen('.tournament.score_updated', (payload) => {
  // payload.matches: snapshot tỉ số hiện tại của các trận trong giải
});

// Đội vừa được điền vào vòng trong (sau khi BTC xác nhận trận vòng trước)
channel.listen('.tournament.advanced', (payload) => {
  // payload.matches: danh sách trận vòng sau + tên đội/member
});

// Khi rời màn:
channel.stopListening('.tournament.score_updated');
channel.stopListening('.tournament.advanced');
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
      "is_going_on": true,
      "status": "pending",
      "is_finished": false
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
| `matches[].status` | string | Trạng thái trận: `pending` / `completed` / `disputed` |
| `matches[].is_finished` | bool | `status === 'completed'` hoặc `live_status === 'finish'` |

**Cách FE áp dụng payload:** với mỗi phần tử trong `matches`, tìm trận trùng `match_id` trong list trận của giải (đã tải từ `/api/livescore/tournaments/{id}/matches`) và ghi đè `team1_score`, `team2_score`, `live_status`, `is_going_on`, `status`, `is_finished`. Trận nào không có trong list thì bỏ qua.

**Lưu ý:**
- Payload **có** `status` và `is_finished` → sau khi confirm, FE không cần gọi lại API để đồng bộ 2 field này.
- `team1_score` / `team2_score` ở payload và ở response API lấy trận cùng nghĩa (điểm set hiện tại), nên có thể trộn dữ liệu 2 nguồn.
- Cần `window.Echo` đã khởi tạo (xem `resources/js/bootstrap.js`; cấu hình qua biến `VITE_PUSHER_*`). Nếu chưa có Echo, FE bỏ qua realtime và chỉ hiển thị dữ liệu lúc gọi API.
- Hiện **chưa có** cơ chế poll dự phòng khi Pusher rớt kết nối. Muốn lấy lại dữ liệu mới nhất, gọi lại API lấy trận của giải tương ứng.

### Gợi ý triển khai phía FE (màn `/tools/livescore`)

- Subscribe kênh `tournament.{id}` **chỉ khi người dùng đang xem giải đó** → tránh nhận nhiễm.
- So sánh `payload.matches[].version` với `version` của trận trong list: nếu `payload.version < list.version` thì bỏ qua event.
- Ghi đè thẳng `status` / `is_finished` — không cần gọi lại API để đồng bộ 2 field này.
- Tên đội của trận vòng trong vừa được BTC xác định đi qua event `.tournament.advanced` (mục dưới) → không cần debounce gọi lại `/matches`.
- Trạng thái kết nối Pusher: `Echo.connector.pusher.connection.bind('state_change', ...)` → hiển thị badge "Realtime" / "Mất kết nối" cho BTC.

---

### Sự kiện `tournament.advanced` — đội vừa điền vào vòng trong

| Thuộc tính | Giá trị |
|---|---|
| Kênh | `tournament.{tournament_id}` — **cùng channel** với `tournament.score_updated`, không cần subscribe thêm |
| Tên sự kiện (server) | `tournament.advanced` |
| Tên sự kiện (client) | `.tournament.advanced` |
| Chế độ phát | `ShouldBroadcastNow` |
| Event class | `app/Events/TournamentAdvanced.php` |
| Bắn từ | `MatchesController::confirmResult()` và `MatchesController::advanceTeamManual()` |

**Payload:**

```json
{
  "tournament_id": 1358,
  "trigger_match_id": 13012,
  "matches": [
    {
      "match_id": 12704,
      "round": 2,
      "team1": {
        "id": 1852,
        "name": "Đội số 1",
        "members": [
          {
            "id": 58,
            "full_name": "Thang Tagz",
            "name": "Thang Tagz",
            "avatar_url": "https://picki.vn/storage/avatars/xxx.jpg",
            "avatar": "https://picki.vn/storage/avatars/xxx.jpg",
            "is_guest": false
          }
        ]
      },
      "team2": {
        "id": 1854,
        "name": "Đội số 3",
        "members": []
      }
    }
  ]
}
```

| Field | Kiểu | Mô tả |
|---|---|---|
| `tournament_id` | int | ID giải — trùng với `{id}` của kênh |
| `trigger_match_id` | int | Trận vừa chốt kết quả, làm sinh ra event này |
| `matches` | array | Các trận vòng **sau** vòng của `trigger_match_id`, đã có ít nhất 1 đội |
| `matches[].match_id` | int | ID trận — khớp với `match_id` trong response `/matches` |
| `matches[].round` | int | Vòng của trận đó |
| `matches[].team1` | object | Đội nhà — shape **giống hệt** `team1` trong response `/matches` |
| `matches[].team2` | object | Đội khách — shape giống `team2` trong response `/matches` |

**Về shape của `team1` / `team2`:**

- Được tạo bằng đúng hàm `LivescorePickerController::formatPickerTeam()` mà API `/matches` dùng → payload và response API **cùng cấu trúc**, FE có thể `replace` thẳng mà không mất field.
- Trận chỉ mới có **1 đội** (điển hình là bán kết ngay sau khi chốt vòng bảng) → đội còn lại là shape TBD: `{ "id": null, "name": "TBD", "members": [] }`, **không phải** `null`.
- `members` gồm cả user thật (`is_guest: false`) và guest (`is_guest: true`) — dùng cho hiển thị tên/avatar người chơi và tìm kiếm theo tên trên màn picker.

**Cách FE áp dụng payload:** với mỗi phần tử trong `matches`, tìm trận trùng `match_id` trong list của giải rồi ghi đè `team1` / `team2` **nếu `id` khác `null`** (bỏ qua shape TBD để không xoá mất đội đang có). Trận không có trong list thì bỏ qua.

```js
const isKnown = (t) => t && t.id != null; // TBD = chưa có đội, bỏ qua

const applyAdvanced = (payload) => {
  const list = matchesByTournament[payload.tournament_id];
  if (!list?.length) return;

  const byId = new Map(payload.matches.map((r) => [r.match_id, r]));
  let touched = false;

  for (const m of list) {
    const row = byId.get(m.match_id);
    if (!row) continue;
    if (isKnown(row.team1)) { m.team1 = row.team1; touched = true; }
    if (isKnown(row.team2)) { m.team2 = row.team2; touched = true; }
  }

  if (touched) matchesByTournament[payload.tournament_id] = [...list];
};
```

**Lưu ý:**
- Chỉ gửi trận có `round > round(trigger_match_id)` — trận vừa ghi đội thắng vào đó đã đi qua `tournament.score_updated`, không gửi lại.
- Payload này **không** đồng bộ các field khác của trận (`round`, `scheduled_at`, `status`…) — nếu cần thì gọi lại `/matches`.
- Trận BTC **tạo mới** không có trong list sẽ không được patch (event không mang phần tử mới). Hiện BTC tạo trận ở màn khác nên không ảnh hưởng; nếu sau này cho tạo trận ngay trong `/tools/livescore` thì cần bắn thêm event khi tạo.
- Màn chiếu `/live-score/...` **không** nghe event này (chỉ nghe `score_updated`) — trận vòng trong sẽ chỉ cập nhật tên khi màn chiếu được mở lại / refetch.

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

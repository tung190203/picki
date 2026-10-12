
## Endpoint

```
GET /api/players/search[?tab=user&keyword=...&sub_tab=...&lat=...&lng=...&radius=...&per_page=...&page=...]
```

`tab=user` là default của route `/api/players/search` nên có thể bỏ qua. Tương đương với `GET /api/search?tab=user`.

### Query parameters

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `tab` | string | ✓ | `user` (mặc định khi gọi `/api/players/search`) |
| `keyword` | string |   | Tìm theo `full_name`, `address`, tên CLB đang tham gia |
| `per_page` | int |   | Số user/trang (mặc định: trả ALL nếu bỏ trống) |
| `page` | int |   | Trang (khi có `per_page`) |
| `lat`, `lng` | float |   | Tâm tính `distance` (km) và lọc theo bán kính. Với `sub_tab=suggest` không gửi → fallback về `Auth::user()->latitude/longitude` |
| `radius` | float |   | Bán kính lọc (km) cho sub_tab khác `suggest` |
| `location_id` | int |   | Lọc theo tỉnh/thành |
| `sub_tab` | string |   | `all` (mặc định), `same_club`, `played_together`, `suggest`, `this_week` |
| `map_mode` | bool |   | Trả ALL + `bounds` cho hiển thị bản đồ |
| `club_id` | int |   | Bắt buộc khi `sub_tab=same_club` — lọc user trong CLB cụ thể |
| `filters[sport_id]` | int |   | Lọc theo môn thể thao |

### Authentication

- Bắt buộc cho `sub_tab=suggest` (cần `Auth::id()` để tính 3 nhóm).
- Các sub_tab khác: anonymous được, nhưng `withInteractionStatus` sẽ trả `is_follow=false` mặc định.

---

## Sub-tab overview

| `sub_tab` | Mục đích | Trả về | Phân trang |
|---|---|---|---|
| `all` (mặc định) | Tìm user theo keyword + filter địa lý | Flat list | Có |
| `played_together` | User đã chơi cùng nhiều trận nhất | Flat list, có `relation` | Có |
| `same_club` | Thành viên trong cùng 1 CLB (`club_id`) hoặc gộp tất cả CLB của current user nếu không truyền `club_id` | Flat list | Có |
| `this_week` | User tương tác tuần này | Flat list | Có |
| `suggest` | Gợi ý cá nhân hoá 3 nhóm | **Flat list đã merge** (mỗi user có `category` để FE group) | Không (đã cap 15/nhóm) |

---

## sub_tab = suggest (flat list — đã merge từ 3 nhóm)

Trả về 1 flat list, mỗi user có `category` enum (`played_tournament` | `same_club` | `nearby`) để FE tự group. Thứ tự ưu tiên: `played_tournament` (15) → `same_club` (15) → `nearby` (15) — user không trùng giữa các nhóm. Tổng tối đa 30 user.

### Query

```
GET /api/players/search?tab=user&sub_tab=suggest
```

Không cần `keyword`/`per_page`/`page`/`map_mode`. `lat/lng` optional — nếu thiếu sẽ fallback về toạ độ user đang login trong DB.

### Response

```json
{
  "status": true,
  "message": "Tìm kiếm thành công",
  "data": {
    "data": [ /* User[] — flat, mỗi user có field `category` */ ],
    "meta": {
      "current_page": 1,
      "last_page":    1,
      "per_page":     30,
      "total":        30
    }
  }
}
```

User xuất hiện ở nhóm trước sẽ bị loại ở nhóm sau (tránh trùng). FE group theo `category` để hiển thị section.

| Group | Logic | Sort | Cap |
|---|---|---|---|
| `played_tournament` | Users đã chơi cùng current user nhiều trận nhất. Tính qua 4 nguồn: `match_histories` (quick_matches), `mini_team_members` (mini tournaments team-based), `mini_participants` (mini tournaments solo), `team_members` (tournaments team-based) | Tổng `matches_together + mini_tournaments_together + tournaments_together` desc | 15 |
| `same_club` | Users cùng CLB active với current user (qua `club_members` với `status=active` + `deleted_at=null`) | `clubs_together` desc | 15 |
| `nearby` | Users trong bán kính 10km quanh `(lat, lng)` (Haversine). Bỏ qua nếu không có lat/lng | `distance` asc | 15 |

User đã xuất hiện ở nhóm trước sẽ bị loại ở nhóm sau (tránh trùng).

#### User shape (mỗi item trong 3 nhóm)

```json
{
  "id": 827,
  "full_name": "Nghĩa Trung",
  "avatar_url": "https://cdn.picki.vn/avatars/...",
  "gender": 1,
  "gender_text": "Nam",
  "age_group": "Người lớn",
  "visibility": "open",
  "address": "...",
  "is_online": false,
  "primary_badge": { "type": "VERIFIED", "icon_url": null },
  "vn_rank": null,
  "vndupr_score": "2.192",
  "win_rate": 48.72,
  "total_matches": 39,
  "distance": 1.1,
  "latitude": "21.0343435",
  "longitude": "105.7958666",
  "sports": [ /* UserSport[] */ ],
  "clubs":  [ /* Club mini {id,name,logo_url}[] */ ],
  "is_follow": false,
  "marker_type": "user",
  "is_guest": false,

  "category": "nearby",
  "category_text": "Ở gần bạn",
  "relation": {
    "matches_together": 0,
    "mini_tournaments_together": 0,
    "tournaments_together": 0,
    "clubs_together": 0
  }
}
```

3 field đặc thù cho suggest mode:

| Field | Type | Mô tả |
|---|---|---|
| `category` | string | Enum: `played_tournament` \| `same_club` \| `nearby` |
| `category_text` | string | Label tiếng Việt cho category: `"Hay chơi cùng kèo giải"` \| `"Cùng CLB"` \| `"Ở gần bạn"` |
| `distance` | float\|null | Khoảng cách (km) từ anchor `(lat, lng)` đến user. Tính Haversine cho mọi user có `latitude/longitude`; null nếu user không có toạ độ |
| `relation.matches_together` | int | Số trận quick_match đã chơi cùng (từ `match_histories`) |
| `relation.mini_tournaments_together` | int | Số mini tournament đã chơi cùng (team + participant) |
| `relation.tournaments_together` | int | Số tournament chính thức đã chơi cùng |
| `relation.clubs_together` | int | Số CLB chung (tính ở step `attachRelations`) |

---

## sub_tab = played_together (flat list)

Trả về user đã chơi cùng current user nhiều nhất, có thêm `relation` block tương tự suggest. Tuy nhiên sub_tab này **không phải 3-bucket** — nó flat, có phân trang.

```
GET /api/players/search?tab=user&sub_tab=played_together&per_page=20&page=1
```

Response giống search thường (flat array + meta pagination), mỗi user có thêm `relation`.

**Quy tắc lọc**: chỉ trả về user có `matches_together + mini_tournaments_together + tournaments_together > 0` (tức là đã từng chơi cùng). Lấy từ 4 nguồn giống `played_tournament` group của suggest mode. Cap tối đa `GROUP_LIMIT` (15) user. Anonymous → trả `[]`.

Sort theo tổng matches desc (cùng tiêu chí với nhóm `played_tournament` của suggest).

---

## sub_tab = same_club

```
GET /api/players/search?tab=user&sub_tab=same_club&club_id=42
```

- `club_id` bắt buộc. Thiếu → trả `[]` (controller whereRaw 1=0).
- Bỏ qua `visibleFor` filter (ai trong club đều thấy nhau).

---

## Anonymous behavior

| sub_tab | Khi chưa login |
|---|---|
| `all` | OK, trả user public. `is_follow=false` |
| `played_together` | OK (không có "current user" để so) |
| `same_club` | OK |
| `suggest` | **Trả cả 3 nhóm rỗng** — service cần `userId` |

---

---

## Error cases

| Tình huống | Response |
|---|---|
| `sub_tab=suggest` + anonymous | `data: { played_tournament: [], same_club: [], nearby: [] }` |
| `sub_tab=suggest` + user chưa có lat/lng DB | Giống trên, `nearby: []` |
| `sub_tab=same_club` thiếu `club_id` | `data: []` |
| Lỗi validate | `422` + body lỗi từ `SearchRequest` |

---

## Examples

### Lấy gợi ý 3 nhóm (user đã login)

```bash
curl -X GET "https://api.picki.vn/api/players/search?tab=user&sub_tab=suggest" \
  -H "Authorization: Bearer <jwt>" \
  -H "Accept: application/json"
```

### Lấy gợi ý với anchor GPS cụ thể (override vị trí user)

```bash
curl -X GET "https://api.picki.vn/api/players/search?tab=user&sub_tab=suggest&lat=10.77&lng=106.70" \
  -H "Authorization: Bearer <jwt>"
```

### Tìm user trong CLB

```bash
curl -X GET "https://api.picki.vn/api/players/search?tab=user&sub_tab=same_club&club_id=42" \
  -H "Authorization: Bearer <jwt>"
```

### Search keyword + radius

```bash
curl -X GET "https://api.picki.vn/api/players/search?tab=user&keyword=minh&lat=10.77&lng=106.70&radius=10" \
  -H "Authorization: Bearer <jwt>"
```

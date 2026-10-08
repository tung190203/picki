
## Endpoint

```
GET /api/search?tab=club[&keyword=...&per_page=20&page=1&lat=10.77&lng=106.70&radius=...&sub_tab=...]
```

Hoặc alias rút gọn:

```
GET /api/clubs/search
```

### Query parameters

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `tab` | string | ✓ | Bắt buộc `club` (hoặc dùng alias `/api/clubs/search`) |
| `keyword` | string |   | Tìm theo `name`, `address`, hoặc tên admin/manager/secretary của CLB |
| `per_page` | int |   | Số CLB/trang (mặc định: trả ALL nếu bỏ trống) |
| `page` | int |   | Trang (khi có `per_page`) |
| `lat`, `lng` | float |   | Tâm tính khoảng cách (km) — trả về `distance` |
| `radius` | float |   | Bán kính lọc (km) |
| `minLat`, `maxLat`, `minLng`, `maxLng` | float |   | Lọc theo bounding box |
| `location_id` | int |   | Lọc theo tỉnh/thành |
| `competition_location_id` | int |   | Lọc theo sân cụ thể |
| `sub_tab` | string |   | `all` (mặc định), `following`, `suit_level`, `mine`, `joined`, `friends`, `this_week` |
| `map_mode` | bool |   | Trả về ALL items + `bounds` cho hiển thị bản đồ |
| `is_verified` | bool |   | Lọc CLB đã xác minh |
| `joined_only` | bool |   | Chỉ trả CLB user hiện tại đã join |

## Response shape

```json
{
  "data": [
    {
      "id": 1,
      "name": "Nhà xe tôi thứ 4",
      "logo_url": "https://cdn.picki.vn/clubs/abc.png",
      "status": 1,
      "is_verified": true,
      "is_public": true,
      "created_by": 5,

      "address": "63 Nguyễn Huy Tưởng, Thanh Xuân, Hà Nội",
      "latitude": 20.99,
      "longitude": 105.80,
      "distance": 1.2,

      "primary_home_court": {
        "id": 10,
        "name": "Sân Pickleball ABC",
        "address": "...",
        "latitude": 20.99,
        "longitude": 105.80
      },

      "recurring_schedule_text": "Sinh hoạt T2, T4, T6 · 19:00–22:00",

      "recruitment_status": "open",
      "recruitment_status_text": "Đang tuyển thành viên",

      "quantity_members": 36,
      "followers_count": 118,
      "is_following": false,
      "is_member": true,
      "is_admin": false,
      "has_pending_request": false,
      "has_invitation": false,

      "score_range": { "min": 1.6, "max": 2.6 },
      "score_range_text": "1.6-2.6",

      "score_match": null,

      "active_matches_count": 5,
      "active_tournaments_count": 2,
      "announcements_count": 0,

      "admin": {
        "id": 5,
        "full_name": "Nguyễn Văn A",
        "avatar_url": "https://...",
        "vndupr_score": 3.5
      },

      "total_mini_tournaments_count": 37,
      "total_tournaments_count": 5,

      "profile": {
        "description": "...",
        "cover_image_url": "https://..."
      },

      "marker_type": "club"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 1,
    "total": 1
  }
}
```

## Field reference

### Identity & display

| Field | Type | Mô tả |
|---|---|---|
| `id` | int | Club ID |
| `name` | string | Tên CLB |
| `logo_url` | string\|null | Logo (full URL) |
| `status` | int | `1` = Active, khác theo `ClubStatus` enum |
| `is_verified` | bool | Đã xác minh |
| `is_public` | bool | Công khai (private CLB chỉ hiện với member) |
| `created_by` | int\|null | User ID của creator |

### Location

| Field | Type | Mô tả |
|---|---|---|
| `address` | string\|null | Địa chỉ CLB |
| `latitude`, `longitude` | float\|null | Tọa độ CLB |
| `distance` | float\|null | Khoảng cách (km) từ `lat`/`lng` của viewer — chỉ có khi request truyền `lat`/`lng` |
| `primary_home_court` | object\|null | Sân nhà chính (position 0) — `null` nếu chưa set |

### Members & follow

| Field | Type | Mô tả |
|---|---|---|
| `quantity_members` | int | Tổng thành viên (real + virtual guest) |
| `followers_count` | int | Số follower (đã trừ member trùng) |
| `is_following` | bool | Viewer có đang follow CLB này không |
| `is_member` | bool | Viewer có phải member (joined + active) |
| `is_admin` | bool | Viewer có phải admin (role hoặc creator) |
| `has_pending_request` | bool | Viewer đã gửi yêu cầu join đang chờ |
| `has_invitation` | bool | Viewer có lời mời đang chờ |

### Schedule & recruitment

| Field | Type | Mô tả |
|---|---|---|
| `recurring_schedule_text` | string\|null | Lịch sinh hoạt định kỳ (đã truncate 100 ký tự) |
| `recruitment_status` | string | `open` \| `closed` \| `invite_only` |
| `recruitment_status_text` | string\|null | Text tiếng Việt cho UI |

### Skill level

| Field | Type | Mô tả |
|---|---|---|
| `score_range` | object\|null | `{ min: float, max: float }` — MIN/MAX vndupr_score của member active |
| `score_range_text` | string\|null | `"1.6-2.6"` — text format cho UI |
| `score_match` | object\|null | Chỉ có khi `sub_tab=suit_level` — `{ user_score, tolerance, delta }` |

### Counts

| Field | Type | Mô tả |
|---|---|---|
| `active_matches_count` | int | Mini-tournament đang `DRAFT` hoặc `OPEN` |
| `active_tournaments_count` | int | Tournament đang `DRAFT` hoặc `OPEN` |
| `announcements_count` | int | Số thông báo chưa đọc (chỉ khi có `user_id`) |
| `total_mini_tournaments_count` | int | **Tổng mini-tournament đã tổ chức (toàn bộ lịch sử, không lọc status)** |
| `total_tournaments_count` | int | **Tổng tournament đã tổ chức (toàn bộ lịch sử, không lọc status)** |

### Admin (Phase 2 mới)

| Field | Type | Mô tả |
|---|---|---|
| `admin` | object\|null | **Creator CLB** (từ `clubs.created_by`). Slim format: `{id, full_name, avatar_url, vndupr_score}`. `null` khi creator bị soft-delete. |

### Profile

| Field | Type | Mô tả |
|---|---|---|
| `profile` | object\|null | `{ description, cover_image_url }` |
| `marker_type` | string | Cố định `"club"` (dùng cho map view) |

## Total counts — phân biệt

| | `active_*_count` | `total_*_count` |
|---|---|---|
| Lọc status | chỉ `DRAFT`/`OPEN` | **tất cả status (history)** |
| Query | `withCount + closure` | `withCount` thường |
| Dùng cho | "đang diễn ra" | "đã tổ chức tổng cộng" |

## N+1 guard

Search Club đã pass test ceiling 35 queries (20 CLB, 1 viewer). Tất cả data trong `toArray()` đều đã được pre-load:

- `creator` / `admin` — `->with(['creator', 'members'])` ở query
- `total_*_count` — `withCount` ở query (1 aggregate)
- `followers_count`, `is_following` — `ClubSearchEnricher::attachFollowersCount/IsFollowing` (1 query/batch)
- `skill_level` — `ClubService::attachSkillLevel` (1 query)
- `primary_home_court` — `ClubSearchEnricher::attachPrimaryHomeCourt` (1 query)

Không có query nào phát sinh trong `SearchClubResource::toArray()`.

## Examples

### Search 5 CLB gần nhất

```
GET /api/search?tab=club&lat=20.99&lng=105.80&radius=10&per_page=5
```

### Search CLB user đang follow

```
GET /api/search?tab=club&sub_tab=following
```

### Search CLB theo trình độ phù hợp (±0.5)

```
GET /api/search?tab=club&sub_tab=suit_level
```

### Search CLB có chứa keyword "pickleball"

```
GET /api/search?tab=club&keyword=pickleball
```

### Map view (ALL matching items)

```
GET /api/search?tab=club&map_mode=true&lat=20.99&lng=105.80&radius=20
```

## Tab gợi ý — `?tab=club&sub_tab=suggest`

Cùng endpoint `/api/search`, truyền `sub_tab=suggest` thay vì gọi route riêng. Trả về tối đa 30 CLB theo 4 nhóm ưu tiên, mỗi item có thêm `category` + `category_text` để FE gom nhóm trên client.

```
GET /api/search?tab=club&sub_tab=suggest
```

### Headers / params

| Param | Vị trí | Required | Mô tả |
|---|---|---|---|
| `Authorization` | header | ✓ | Bearer token (401 nếu guest) |
| `lat`, `lng` | query |   | Vĩ độ/kinh độ viewer (để lấy nhóm `nearby`) |

> `per_page` của client bị bỏ qua — service cap 30 và trả 1 trang duy nhất.

### 4 nhóm ưu tiên

| # | `category` | `category_text` | Quy tắc |
|---|---|---|---|
| 1 | `friend_in_club` | CLB có bạn bè của bạn | Member của CLB này cũng đang follow bạn |
| 2 | `following` | CLB bạn đang theo dõi | Viewer đang follow |
| 3 | `suit_level` | CLB hợp trình độ của bạn | `score_range` của CLB nằm trong ±0.5 vndupr so với viewer |
| 4 | `nearby` | CLB gần bạn | Trong bán kính mặc định, sort theo `distance` |

### Response shape

```json
{
  "data": [
    {
      "id": 1,
      "name": "CLB Pickleball Hà Nội",
      "logo_url": "https://...",

      "address": "63 Nguyễn Huy Tưởng",
      "distance": 1.2,
      "primary_home_court": { "id": 10, "name": "...", "address": "...", "latitude": 20.99, "longitude": 105.80 },

      "quantity_members": 36,
      "followers_count": 118,
      "is_following": true,
      "is_member": false,
      "is_admin": false,

      "score_range": { "min": 1.6, "max": 2.6 },
      "score_range_text": "1.6-2.6",
      "score_match": { "user_score": 2.0, "tolerance": 0.5, "delta": 0.4 },

      "recurring_schedule_text": "Sinh hoạt T2, T4, T6 · 19:00–22:00",
      "recruitment_status": "open",
      "recruitment_status_text": "Đang tuyển thành viên",

      "active_matches_count": 5,
      "active_tournaments_count": 2,
      "announcements_count": 0,

      "admin": { "id": 5, "full_name": "...", "avatar_url": "...", "vndupr_score": 3.5 },

      "total_mini_tournaments_count": 37,
      "total_tournaments_count": 5,

      "category": "friend_in_club",
      "category_text": "CLB có bạn bè của bạn"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 12, "total": 12 }
}
```

> **Body bên trong từng item** là toàn bộ fields của Search Club (Phase 1 + Phase 2 admin/total counts) — chỉ thêm 2 field `category` + `category_text` ở top level.

### Response rỗng (200, không phải 404)

```json
{ "data": [], "meta": { "current_page": 1, "last_page": 1, "per_page": 0, "total": 0 } }
```

### Response chưa đăng nhập

```
HTTP 401
{ "message": "Unauthorized" }
```

### Cap

- Tổng 4 nhóm tối đa **30 CLB** (overflow: trả hết 30, không chia rõ từng nhóm).
- `nearby` sort theo `distance` tăng dần.

### So sánh với search thường

| | `/api/search?tab=club` (sub_tab khác) | `/api/search?tab=club&sub_tab=suggest` |
|---|---|---|
| Auth | Tùy chọn | **Bắt buộc** (401 nếu guest) |
| Trả gì | Tất cả CLB (filter theo param) | 4 nhóm ưu tiên (cap 30) |
| Field mỗi item | Search Club full card | Search Club full card + `category` + `category_text` |
| `meta` | `{ current_page, last_page, per_page, total }` | `{ current_page: 1, last_page: 1, per_page, total }` |
| Phân trang | Có (`page`, `per_page`) | Không (1 page, `per_page` client bị bỏ) |

### N+1 guard

Suggest cũng đi qua `SearchClubResource`, nên các pre-load giống search: `creator`, `members`, `withCount`, `attachFollowersCount`, `attachPrimaryHomeCourt`. Ceiling 80 queries đã pass test 30 CLB.

### Tests

- `ClubCardFieldsTest::test_suggest_endpoint_returns_all_required_card_fields` — kiểm tra đủ field + category mapping
- `ClubQueryCountTest::test_suggest_30_clubs_stays_under_ceiling` — N+1 ceiling

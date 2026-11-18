# Search Court API (`tab=court`)

Tìm kiếm sân đấu (Competition Locations) kèm danh sách giải đấu & kèo đấu đang mở/sắp diễn ra tại sân.

---

## Endpoints

- `GET /api/search?tab=court` (`SearchV2Controller@search`)
- `GET|POST /api/courts/search` (alias với `tab=court`)

### Query Params chính

| Param | Type | Description |
|-------|------|-------------|
| `tab` | string | `court` |
| `sub_tab` | string | `all` (Tất cả) \| `nearby` (Gần tôi) \| `has_event_today` (Có kèo hôm nay) \| `ongoing` (Đang diễn ra) \| `saved` (Đã lưu) |
| `keyword` | string | Tìm theo tên sân, địa chỉ hoặc tên địa phương |
| `lat`, `lng` | float | Toạ độ neo để tính `distance` (km) và sort gần nhất |
| `radius` | float | Bán kính tìm kiếm (km) |
| `filters[sport_id]` | int | Lọc theo môn thể thao |
| `filters[location_id]` | int | Lọc theo khu vực |
| `filters[number_of_yards]` | int | Số sân con tối thiểu |
| `filters[yard_type]` | int[] | Loại sân |
| `filters[facility_id]` | int[] | Tiện ích |
| `filters[distance]` | float[2] | Khoảng cách `[min_km, max_km]` |
| `filters[is_followed]` | bool | Lọc sân đã follow |

---

## Response Item (`SearchCourtResource`)

```json
{
  "id": 12,
  "name": "Pick City 11A Nguyễn Khuyến",
  "image": "https://...",
  "address": "11A Nguyễn Khuyến, Hà Nội",
  "latitude": 21.0285,
  "longitude": 105.8542,
  "phone": "0901234567",
  "opening_time": "06:00",
  "closing_time": "23:00",
  "number_of_yards": 6,
  "sports": [
    { "id": 1, "name": "Pickleball", "icon": "..." }
  ],
  "facilities": [
    { "id": 1, "name": "Bãi đỗ xe", "description": "..." }
  ],
  "distance": 1.4,
  "is_saved": false,
  "marker_type": "court",
  "active_matches_count": 2,
  "active_tournaments_count": 1,
  "active_tournaments": [
    {
      "id": 101,
      "name": "Giải Mở Rộng",
      "type": "tournament",
      "status": "open",
      "start_date": "2026-11-20",
      "created_by": {
        "id": 5,
        "full_name": "Nguyễn Văn A",
        "avatar_url": "https://..."
      }
    }
  ],
  "active_mini_tournaments": [
    {
      "id": 201,
      "name": "Kèo tối thứ 3",
      "status": 1,
      "start_date": "2026-11-17",
      "created_by": {
        "id": 8,
        "full_name": "Trần Văn B",
        "avatar_url": "https://..."
      }
    }
  ]
}
```

### Lưu ý thay đổi payload `created_by` (2026-11-17)
- `active_tournaments[].created_by` và `active_mini_tournaments[].created_by` trả object rút gọn `{ id, full_name, avatar_url }` (hoặc `null`) thay cho full `UserResource` (giống `SearchMatchResource` và `SearchTournamentResource`) để tránh N+1 query khi load danh sách sân.
- Query `TAB_COURT` dùng `CompetitionLocation::withSearchRelations()` để `withCount('competitionLocationYards')`, chỉ `select` các cột cần thiết của `tournaments` / `miniTournaments` và eager-load `creator:id,full_name,avatar_url`.

---

## APIs màn Chi tiết 1 sân đấu (`CompetitionLocationController`)

### 1. Lấy danh sách kèo đấu & giải đấu của sân

```
GET /api/competition-locations/{id}/events
```

| Query Param | Type | Required | Description |
|---|---|---|---|
| `date` | string (`YYYY-MM-DD`) | ❌ | Lọc kèo/giải diễn ra trong ngày đó. Bỏ trống = lấy tất cả kèo/giải **sắp diễn ra & đang diễn ra** |
| `type` | string | ❌ | `all` (mặc định) \| `mini_tournament` \| `tournament` |

**Response:**

```json
{
  "status": true,
  "message": "Lấy danh sách kèo đấu và giải đấu tại sân thành công",
  "data": {
    "competition_location_id": 12,
    "date": "2026-11-17",
    "mini_tournaments_count": 1,
    "tournaments_count": 1,
    "mini_tournaments": [
      {
        "id": 201,
        "name": "Kèo tối thứ 3",
        "type": "mini_tournament",
        "status": 1,
        "session_status": 0,
        "start_time": "2026-11-17T12:00:00.000000Z",
        "end_time": "2026-11-17T14:00:00.000000Z",
        "start_date": "2026-11-17",
        "max_players": 16,
        "participants_count": 10,
        "has_fee": true,
        "fee_amount": 80000,
        "created_by": {
          "id": 8,
          "full_name": "Trần Văn B",
          "avatar_url": "https://..."
        }
      }
    ],
    "tournaments": [
      {
        "id": 101,
        "name": "Giải Mở Rộng",
        "type": "tournament",
        "status": "open",
        "start_date": "2026-11-17",
        "end_date": "2026-11-18",
        "poster_url": "https://...",
        "max_players": 32,
        "participants_count": 20,
        "created_by": {
          "id": 5,
          "full_name": "Nguyễn Văn A",
          "avatar_url": "https://..."
        }
      }
    ]
  }
}
```

### 2. Lấy danh sách CLB đang chọn sân này làm sân nhà (trả all, không phân trang)

```
GET /api/competition-locations/{id}/home-clubs
```

**Response:**

```json
{
  "status": true,
  "message": "Lấy danh sách CLB sân nhà thành công",
  "data": {
    "competition_location_id": 12,
    "clubs_count": 1,
    "clubs": [
      {
        "id": 3,
        "name": "CLB Pickleball Hà Nội",
        "logo_url": "https://...",
        "cover_image_url": "https://...",
        "address": "Hà Nội",
        "is_verified": true,
        "members_count": 48,
        "created_by": {
          "id": 5,
          "full_name": "Nguyễn Văn A",
          "avatar_url": "https://..."
        }
      }
    ]
  }
}
```

### 3. Lưu / bỏ lưu sân đấu (nút tim)

```
POST /api/competition-locations/{id}/toggle-save
```

> Yêu cầu đăng nhập (`auth:api`). Dùng chung bảng `follows` (`followable_type = CompetitionLocation::class`), đồng bộ trực tiếp với `sub_tab=saved` và `is_saved`.

**Response:**

```json
{
  "status": true,
  "message": "Đã lưu sân đấu",
  "data": {
    "competition_location_id": 12,
    "is_saved": true
  }
}
```

# Partner & Opponent Stats API

Tổng hợp thành tích **đồng đội (partner)** và **đối thủ (opponent)** của một user trong môn Pickleball (sport_id = 1) từ 3 nguồn trận đã hoàn thành:

- `QuickMatch` (trận đấu nhanh) — ghép kèm `MatchHistory`
- `Matches` (giải đấu) — ghép kèm `team_members`
- `MiniMatch` (mini-tournament) — ghép kèm `mini_team_members`

Guest (`is_guest = 1`) bị loại khỏi thống kê.

## Phần 1 — Thuật toán ranking

### Đồng đội (Partner) — xếp theo "đồng đội tốt nhất"

Thứ tự sort (DESC) — `sortByDesc`:

1. `wins - losses` — chỉ số thắng thua ròng. Đánh cùng đồng đội nào thắng nhiều hơn thua thì xếp trên.
2. `win_rate` (tiebreaker) — khi cùng chỉ số, ai có tỉ lệ thắng cao hơn xếp trên.
3. `total_matches` (2nd tiebreaker) — khi vẫn bằng, ai đánh nhiều trận hơn xếp trên.

### Đối thủ (Opponent) — xếp theo "kỳ phùng địch thủ" (worst rival)

Thứ tự sort (DESC) — `sortByDesc`:

1. `losses - wins` — chỉ số thua thắng ròng. Đánh với đối thủ nào thua nhiều hơn thắng thì xếp trên.
2. `100 - win_rate` (tiebreaker) — khi cùng chỉ số, ai có tỉ lệ thua cao hơn xếp trên.
3. `total_matches` (2nd tiebreaker) — khi vẫn bằng, ai đánh nhiều trận hơn xếp trên.

### Lọc nhiễu

Đối tượng có `total_matches < 3` bị loại khỏi kết quả. Tránh trường hợp 1W-0L = 100% win_rate chiếm top.

### Ví dụ

| Đối thủ | W-L | losses-wins | loss_rate | Old rank | New rank |
|---|---|---|---|---|---|
| B | 0W-4L | 4 | 100% | 1 | 1 (giữ nguyên) |
| A | 2W-6L | 4 | 75% | 2 | 2 (giữ nguyên) |
| C | 1W-0L | -1 | 0% | was 1 (100%) | filtered out |
| E | 5W-1L | -4 | 16.7% | 3 (đã ở dưới) | 3 |

Cùng `losses - wins = 4` → tiebreaker `loss_rate` (100% > 75%) → B xếp trên A.

## Phần 2 — API

Route đã khai báo trong `routes/api.php` (đã `auth:api`):

```
GET /api/partners
GET /api/opponents
```

Cả 2 đều có query giống nhau:

| Param | Type | Default | Mô tả |
|---|---|---|---|
| `user_id` | int | `auth()->id()` | Xem top của user nào (để trống = bản thân) |
| `page` | int | 1 | Trang (mặc định trả top 3, muốn nhiều hơn cần paging) |

> Lưu ý: hiện tại controller hardcode `per_page = 3` (luôn chỉ lấy top 3). Chỉ phân trang khi muốn xem các vị trí tiếp theo.

### GET `/api/partners` — Top đồng đội

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Lấy top 3 partner thành công",
  "data": {
    "partners": [
      {
        "user": {
          "id": 5,
          "full_name": "Trần Văn B",
          "visibility": "public",
          "avatar_url": "https://...",
          "thumbnail": "https://...",
          "gender": "male",
          "gender_text": "Nam",
          "play_times": [],
          "sports": [
            {
              "sport_id": 1,
              "sport_icon": "🏓",
              "sport_name": "Pickleball",
              "scores": { "personal_score": "0.000", "dupr_score": "3.500", "vndupr_score": "3.750" },
              "total_matches": 45,
              "total_tournaments": 5,
              "total_mini_tournaments": 12,
              "total_prizes": 3,
              "win_rate": 68.5,
              "performance": 1.2
            }
          ],
          "clubs": []
        },
        "total_matches": 6,
        "wins": 5,
        "losses": 1,
        "win_rate": 83.33,
        "loss_rate": 16.67
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 3,
      "total": 1
    }
  }
}
```

#### Field `partners[]`

| Field | Type | Mô tả |
|---|---|---|
| `user` | object\|null | `UserResource` đã hydrate `sports`, `clubs`. `null` nếu user đã bị xoá. |
| `total_matches` | int | Tổng số trận chung với user này |
| `wins` | int | Số trận thắng khi chung đội |
| `losses` | int | Số trận thua khi chung đội |
| `win_rate` | float | `%` thắng (2 chữ số thập phân) |
| `loss_rate` | float | `%` thua (2 chữ số thập phân) |

Nếu user chưa có đồng đội nào đủ điều kiện → `partners: []`, `meta.total = 0`, message `"Không có partner nào"`.

### GET `/api/opponents` — Top đối thủ (kỳ phùng địch thủ)

#### Response — 200 OK

```json
{
  "status": true,
  "message": "Lấy top 3 opponent thành công",
  "data": {
    "opponents": [
      {
        "user": {
          "id": 12,
          "full_name": "Lê Thị C",
          "...": "(UserResource như trên)"
        },
        "total_matches": 4,
        "wins": 0,
        "losses": 4,
        "win_rate": 0.0,
        "loss_rate": 100.0
      },
      {
        "user": {
          "id": 7,
          "full_name": "Nguyễn Văn D"
        },
        "total_matches": 8,
        "wins": 2,
        "losses": 6,
        "win_rate": 25.0,
        "loss_rate": 75.0
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 3,
      "total": 2
    }
  }
}
```

Field `opponents[]` cùng schema với `partners[]`.

Nếu user chưa có đối thủ nào đủ điều kiện → `opponents: []`, `meta.total = 0`, message `"Không có opponent nào"`.

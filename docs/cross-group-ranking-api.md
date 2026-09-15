## API 1: Lấy Danh sách Ứng viên So sánh

### Endpoint

```
GET /api/tournament-types/{tournamentType}/cross-group-comparison
```

### Mục đích

Trả về danh sách xếp hạng tất cả đội Nhì và Ba từ mọi bảng, kèm thống kê so sánh sau khi loại các trận gặp **đội cuối bảng (Ba)**.

> **Tài liệu liên quan**: [cross-group-ranking-logic.md](./cross-group-ranking-logic.md) — giải thích chi tiết thuật toán xét đội Nhì/Ba, điều kiện áp dụng, và cách loại trận đội cuối bảng.

**Quy tắc loại trận:**
- **Bảng đủ** (k đội > minimum_group_size): loại trận gặp **đội cuối bảng (Ba)** → Nhì được so sánh qua trận gặp Nhất. Điều này loại bỏ handicap không công bằng khi Ba có thể thua nhiều trận hơn.
- **Bảng thiếu** (k đội <= minimum_group_size): không loại gì, tính đầy đủ.

**Ví dụ:**
- Bảng 3 đội (k=3, m=2): loại rank 3 (Ba) → Nhì được so sánh qua trận gặp Nhất.
- Bảng 2 đội (k=2, m=2): không loại gì → Nhì chỉ gặp Nhất, tính đầy đủ.

### Schema Response

```json
{
    "status": true,
    "message": "string",
    "data": {
        "enabled": true,
        "applied": true,
        "comparison_rule": {
            "minimum_group_size": 4,
            "description": "string"
        },
        "qualification": {
            "number_of_groups": 6,
            "knockout_slots": 8,
            "additional_slots": 2,
            "runner_up_candidates": 6,
            "third_place_candidates": 0
        },
        "candidates": [
            {
                "rank": 1,
                "team": {
                    "id": 123,
                    "name": "Đoàn Trần - Radio"
                },
                "group": {
                    "id": 5,
                    "name": "Bảng B",
                    "team_count": 4
                },
                "group_position": 2,
                "candidate_type": "runner_up",
                "matches": {
                    "original": 3,
                    "counted": 3,
                    "excluded": 0
                },
                "statistics": {
                    "wins": 2,
                    "losses": 1,
                    "win_rate": 66.67,
                    "points_for": 35,
                    "points_against": 30,
                    "point_diff": 5,
                    "average_point_difference": 1.67
                },
                "status": "qualified",
                "pending_draw": false,
                "has_excluded_matches": false
            }
        ]
    }
}
```

### Các chỉ số xét hạng (ranking rules) được dùng cho cross-group comparison

Vì Nhì/Ba ở **các bảng khác nhau chưa từng gặp nhau**, không thể so sánh head-to-head giữa chúng. Hệ thống dùng thứ tự ưu tiên:

| Ưu tiên | Rule ID | Tên | Field | Ý nghĩa |
|---|---|---|---|---|
| 1 | 1 | `RANKING_WIN_DRAW_LOSE_POINTS` | `points` | Điểm xếp hạng (Thắng=3, Hòa=1, Thua=0) |
| 2 | 2 | `RANKING_WIN_RATE` | `win_rate` | Tỷ lệ thắng (%) |
| 3 | 3 | `RANKING_SETS_WON` | `sets_diff` | Hiệu số hiệp thắng - hiệp thua |
| 4 | 4 | `RANKING_POINTS_WON` | `point_diff` | Hiệu số điểm (points_for - points_against) |
| 5 | 7 | `RANKING_GOALS_SCORED` | `points_for` | **Tổng điểm/bàn ghi được** — tie-breaker khi 4 chỉ số trên vẫn bằng nhau |
| 6 | 5 | `RANKING_HEAD_TO_HEAD` | H2H | Vô hiệu với cross-group (luôn = 0); vẫn được append để tương thích |
| — | 6 | `RANKING_RANDOM_DRAW` | team_id ASC | Stable fallback khi tất cả rule trên đều bằng |

**Default cross-group ranking**: `[1, 2, 3, 4, 7]` (+ auto-append `5`).

Có thể override bằng cách set `format_specific_config[0].ranking` (mảng các rule ID ở trên). Nếu thiếu `RANKING_GOALS_SCORED` và `RANKING_HEAD_TO_HEAD`, hệ thống sẽ **tự động append**.

`pending_draw = true` khi cùng `candidate_type` và cùng tất cả ranking keys đang xét (tính cả `points_for` mới).

### Các trường Response

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| `enabled` | boolean | Tính năng có được bật trong cấu hình giải đấu |
| `applied` | boolean | Quy tắc có thực sự được áp dụng (bảng không đều + format=Mixed) |
| `comparison_rule.minimum_group_size` | integer | Số đội của bảng nhỏ nhất |
| `qualification.additional_slots` | integer | Số suất cần lấy thêm từ cross-group (knockout_slots - total_from_pool_stage) |
| `candidates[].rank` | integer | Xếp hạng so sánh (1 = tốt nhất) |
| `candidates[].candidate_type` | string | `runner_up` (Nhì) hoặc `third_place` (Ba) |
| `candidates[].matches.excluded` | integer | Số trận bị loại khỏi tính toán |
| `candidates[].status` | string | `qualified` (đi tiếp), `not_qualified` (không đi tiếp), hoặc `not_applicable` |
| `candidates[].pending_draw` | boolean | True nếu bằng nhau trên mọi tiêu chí xếp hạng |
| `candidates[].has_excluded_matches` | boolean | True nếu có trận bị loại |

### Khi không áp dụng

```json
{
    "status": true,
    "message": "...",
    "data": {
        "enabled": true,
        "applied": false,
        "comparison_rule": {
            "minimum_group_size": null,
            "description": null
        },
        "qualification": {
            "number_of_groups": 0,
            "knockout_slots": 0,
            "additional_slots": 0,
            "runner_up_candidates": 0,
            "third_place_candidates": 0
        },
        "candidates": []
    }
}
```

---

## API 2: Lấy Chi tiết Trận của Đội

### Endpoint

```
GET /api/tournament-types/{tournamentType}/cross-group-comparison/{team}/matches
```

### Mục đích

Trả về danh sách đầy đủ các trận vòng bảng của một đội ứng viên cụ thể, có đánh dấu rõ trận nào được tính và trận nào bị loại.

### Schema Response

```json
{
    "status": true,
    "message": "string",
    "data": {
        "team": {
            "id": "123",
            "name": "Duy Nguyễn - Hải Nguyên"
        },
        "group": {
            "id": "3",
            "name": "Bảng A",
            "team_count": 5
        },
        "group_position": 2,
        "candidate_type": "runner_up",
        "comparison": {
            "minimum_group_size": 4,
            "original_matches": 4,
            "counted_matches": 3,
            "excluded_matches": 1,
            "wins": 2,
            "losses": 1,
            "win_rate": 66.67,
            "points_for": 34,
            "points_against": 30,
            "point_diff": 4,
            "average_point_difference": 1.33
        },
        "matches": [
            {
                "id": "456",
                "opponent": {
                    "id": "789",
                    "name": "Huy CAP"
                },
                "opponent_group_position": 4,
                "score": "9 - 11",
                "home_score": 9,
                "away_score": 11,
                "result": "loss",
                "included": true,
                "exclusion_reason": null
            },
            {
                "id": "457",
                "opponent": {
                    "id": "790",
                    "name": "Châu Bùi - datclinic"
                },
                "opponent_group_position": 5,
                "score": "11 - 2",
                "home_score": 11,
                "away_score": 2,
                "result": "win",
                "included": false,
                "exclusion_reason": "Đối thủ xếp hạng 5 trong bảng"
            }
        ]
    }
}
```

### Các chỉ số xét hạng (ranking rules) được dùng cho cross-group comparison

Vì Nhì/Ba ở **các bảng khác nhau chưa từng gặp nhau**, không thể so sánh head-to-head giữa chúng. Hệ thống dùng thứ tự ưu tiên:

| Ưu tiên | Rule ID | Tên | Field | Ý nghĩa |
|---|---|---|---|---|
| 1 | 1 | `RANKING_WIN_DRAW_LOSE_POINTS` | `points` | Điểm xếp hạng (Thắng=3, Hòa=1, Thua=0) |
| 2 | 2 | `RANKING_WIN_RATE` | `win_rate` | Tỷ lệ thắng (%) |
| 3 | 3 | `RANKING_SETS_WON` | `sets_diff` | Hiệu số hiệp thắng - hiệp thua |
| 4 | 4 | `RANKING_POINTS_WON` | `point_diff` | Hiệu số điểm (points_for - points_against) |
| 5 | 7 | `RANKING_GOALS_SCORED` | `points_for` | **Tổng điểm/bàn ghi được** — tie-breaker khi 4 chỉ số trên vẫn bằng nhau |
| 6 | 5 | `RANKING_HEAD_TO_HEAD` | H2H | Vô hiệu với cross-group (luôn = 0); vẫn được append để tương thích |
| — | 6 | `RANKING_RANDOM_DRAW` | team_id ASC | Stable fallback khi tất cả rule trên đều bằng |

**Default cross-group ranking**: `[1, 2, 3, 4, 7]` (+ auto-append `5`).

Có thể override bằng cách set `format_specific_config[0].ranking` (mảng các rule ID ở trên). Nếu thiếu `RANKING_GOALS_SCORED` và `RANKING_HEAD_TO_HEAD`, hệ thống sẽ **tự động append**.

`pending_draw = true` khi cùng `candidate_type` và cùng tất cả ranking keys đang xét (tính cả `points_for` mới).

### Các trường Response

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| `matches[].included` | boolean | True = được tính trong so sánh, False = bị loại |
| `matches[].exclusion_reason` | string\|null | Lý do bị loại bằng tiếng Việt (ví dụ: "Đối thủ xếp hạng 5 trong bảng") |
| `matches[].result` | string | `win` (thắng), `loss` (thua), hoặc `draw` (hòa) |
| `matches[].opponent_group_position` | integer\|null | Thứ hạng cuối cùng của đối thủ trong bảng |

---

## API 3: Lấy Bảng Xếp Hạng (Rankings) — có `need_draw_lots` + `advanced_team_ids`

### Endpoint

```
GET /api/tournament-types/{tournamentType}/rank
```

### Mục đích

Trả về BXH chi tiết cho từng bảng + BXH tổng, kèm **2 field mới** giúp frontend xác định nhóm đang chờ bốc thăm:

- `need_draw_lots` — true khi có cụm đồng hạng cần BTC xử lý.
- `advanced_team_ids` — danh sách team_id đã chắc chắn đi tiếp (loại trừ các vị trí đang chờ bốc thăm).

> **Tài liệu liên quan**: [manual-tiebreaker-api.md](./manual-tiebreaker-api.md) — chỉnh sửa thủ công thứ hạng khi đồng hạng.

### Schema Response (TH có chia bảng)

```json
{
  "status": true,
  "message": "...",
  "data": {
    "group_rankings": [
      {
        "group_id": 1,
        "group_name": "Bảng A",
        "need_draw_lots": true,
        "advanced_team_ids": [101, 102],
        "rankings": [
          {
            "rank": 1,
            "team_id": 101,
            "team_name": "Đoàn Trần - Radio",
            "team_avatar": "...",
            "played": 3,
            "wins": 2,
            "draws": 1,
            "losses": 0,
            "points": 7,
            "points_for": 35,
            "points_against": 30,
            "point_diff": 5,
            "sets_won": 6,
            "sets_lost": 3,
            "sets_diff": 3,
            "win_rate": 66.67,
            "pending_tie": false
          },
          {
            "rank": 2,
            "team_id": 102,
            "team_name": "Duy Nguyễn",
            "points": 7,
            "point_diff": 5,
            "win_rate": 66.67,
            "sets_diff": 3,
            "points_for": 35,
            "pending_tie": true
          }
        ]
      }
    ],
    "overall_rankings": [ /* ... */ ]
  }
}
```

### Các trường mới

| Trường | Kiểu | Mô tả |
|--------|------|--------|
| `need_draw_lots` | boolean | `true` khi trong BXH có >=2 team đồng hạng trên TẤT CẢ ranking keys thực sự (không tính H2H vì cycle). Frontend dùng để hiển thị badge "Chờ bốc thăm" + mở Modal bốc thăm. |
| `advanced_team_ids` | int[] | Danh sách team_id đã được xác định rõ ràng đi tiếp (= top N-1 nếu ranh giới đồng hạng, hoặc top N nếu không). FE dựa vào đây + `advanced_per_group` để tính còn bao nhiêu suất cần bốc thêm. |
| `rankings[].pending_tie` | boolean | `true` nếu team thuộc cụm đồng hạng (>=2 team cùng stats). Frontend dùng để highlight dòng vàng + thêm badge "Đồng hạng". |
| `points_for` | int | ✅ NEW: tổng điểm/bàn ghi được (cho rule GOALS_SCORED). |
| `sets_won`, `sets_lost`, `sets_diff` | int | ✅ NEW: tổng hiệp thắng/thua/hiệu số hiệp (cho rule SETS_WON). |

### Logic `need_draw_lots`

`true` nếu có cụm >=2 team đồng hạng trên TẤT CẢ ranking keys (không tính `RANKING_HEAD_TO_HEAD` + `RANKING_RANDOM_DRAW`).

Đặc biệt: nếu cụm đồng hạng nằm **ở ranh giới N/N+1** (số team đi tiếp) → `need_draw_lots = true` → FE nên ưu tiên mở modal bốc thăm.

### Logic `advanced_team_ids`

- Nếu group có `<= num_advancing` team → trả về TẤT CẢ team.
- Nếu group có `> num_advancing` team + ranh giới đồng hạng → trả về `top (num_advancing - 1)`.
- Nếu group có `> num_advancing` team + không có ranh giới đồng hạng → trả về `top num_advancing`.

`num_advancing` = max(`rank`) của REAL `PoolAdvancementRule` cho group đó (mỗi rule đại diện cho 1 spot: Nhất/Nhì/Ba).

### TH không chia bảng

Response chỉ trả `rankings[]`, không có `need_draw_lots` + `advanced_team_ids` (vì BXH chung toàn giải không có ranh giới đội đi tiếp).

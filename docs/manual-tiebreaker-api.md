# Manual Tiebreaker API — Bốc thăm / Kéo-thả thủ công

## Tổng quan

Khi trong BXH có >=2 đội đồng hạng trên TẤT CẢ ranking keys (không tính H2H vì cycle, không tính RANDOM_DRAW), hệ thống không thể tự xếp. Khi đó BTC có thể dùng API này để:

- Lấy danh sách các cụm đồng hạng (`pending-ties`).
- Chỉ định thứ hạng thủ công cho từng đội trong cụm (`store`).
- Reset khi cần (`destroy`).

Sau khi set, `GroupStandingRanker::rank()` + `CrossGroupComparisonService::rankCandidates()` tự động áp dụng manual rank vào BXH + cross-group candidates.

---

## Auth

Tất cả endpoints yêu cầu:
- Bearer token (`auth:api` middleware)
- User phải là BTC/admin của giải (`TournamentPermission::canOperateBracket`).

Nếu không đủ quyền → 403 `BusinessException("Bạn không có quyền chỉnh sửa BXH", 403)`.

---

## Endpoint 1: Lấy các cụm đồng hạng

### URL

```
GET /api/tournament-types/{tournamentType}/groups/{group}/pending-ties
```

### Response (200)

```json
{
  "status": true,
  "message": "...",
  "data": {
    "group_id": 1,
    "pending_ties": [
      {
        "indices": [1, 2],
        "team_ids": [102, 103],
        "stats": {
          "points": 7,
          "win_rate": 66.67,
          "sets_diff": 3,
          "point_diff": 5,
          "points_for": 35
        },
        "teams": [
          { "team_id": 102, "team_name": "Duy Nguyễn", "team_avatar": "..." },
          { "team_id": 103, "team_name": "Huy CAP", "team_avatar": "..." }
        ]
      }
    ],
    "has_manual_rank": false,
    "manual_ranks": []
  }
}
```

### Mô tả field

| Field | Kiểu | Mô tả |
|---|---|---|
| `pending_ties[].indices` | int[] | Vị trí 0-based trong standings array. |
| `pending_ties[].team_ids` | int[] | Danh sách team_id thuộc cụm. |
| `pending_ties[].stats` | object | Stats chung của cả cụm (tất cả team đều bằng nhau trên các key này). |
| `pending_ties[].teams[]` | object[] | Thông tin hiển thị cho UI. |
| `has_manual_rank` | boolean | True nếu BTC đã set manual rank cho group này. |
| `manual_ranks[]` | object[] | Manual ranks hiện tại (nếu đã set) — UI dùng để pre-fill modal. |

---

## Endpoint 2: Lưu manual ranks (intra-group)

### URL

```
POST /api/tournament-types/{tournamentType}/groups/{group}/manual-tiebreaker
```

### Request Body

```json
{
  "rankings": [
    { "team_id": 102, "manual_rank": 1 },
    { "team_id": 103, "manual_rank": 2 }
  ]
}
```

### Validation

- `rankings` required, mảng có >= 2 phần tử.
- `rankings[].team_id` required, integer >= 1.
- `rankings[].manual_rank` required, integer >= 1, **phải duy nhất** trong mảng.
- Tất cả team_id phải thuộc group.
- Tất cả team_id phải nằm trong **cùng 1 cụm đồng hạng** của standings hiện tại.

Nếu vi phạm → 422 `BusinessException`.

### Response (200)

```json
{
  "status": true,
  "message": "Đã lưu thứ hạng thủ công",
  "data": {
    "message": "Đã lưu thứ hạng thủ công",
    "group_id": 1,
    "tournament_type_id": 5
  }
}
```

### Effect

- Bảng `manual_tiebreaker_ranks` sẽ xóa tất cả rank cũ của group này (candidate_type=NULL) và insert các row mới.
- Ngay lập tức, lần gọi `GET /api/tournament-types/{type}/rank` tiếp theo sẽ trả BXH với các team được sort theo `manual_rank ASC`.
- Tương tự, cross-group candidates cũng sẽ dùng thứ tự manual khi so sánh Nhì/Ba.

---

## Endpoint 3: Reset manual ranks (intra-group + cross-group)

### URL

```
DELETE /api/tournament-types/{tournamentType}/groups/{group}/manual-tiebreaker
```

### Response (200)

```json
{
  "status": true,
  "message": "Đã reset manual ranks",
  "data": {
    "message": "Đã reset manual ranks",
    "group_id": 1
  }
}
```

### Effect

- Xóa TOÀN BỘ manual ranks của group này (cả intra-group lẫn cross-group).
- BXH + cross-group sẽ fallback về team_id ASC (deterministic).

---

## Endpoint 4: Lưu manual ranks cho cross-group candidates (Nhì/Ba)

### URL

```
POST /api/tournament-types/{tournamentType}/groups/{group}/manual-tiebreaker/cross
```

### Request Body

```json
{
  "candidate_type": "runner_up",
  "rankings": [
    { "team_id": 102, "manual_rank": 1 },
    { "team_id": 103, "manual_rank": 2 }
  ]
}
```

### Validation

- `candidate_type` required, in: `runner_up` | `third_place`.
- `rankings` required, mảng có >= 2 phần tử (giống Endpoint 2).

### Effect

- Lưu manual ranks cho các team ở vị trí Nhì/Ba của group này (dùng khi so sánh chéo bảng với các Nhì/Ba ở bảng khác).
- `rankCandidates()` sẽ query các row này khi sort candidates, ưu tiên `manual_rank ASC`.

---

## Endpoint 5: Reset manual ranks cho cross-group candidates

### URL

```
DELETE /api/tournament-types/{tournamentType}/groups/{group}/manual-tiebreaker/cross
```

### Request Body

```json
{
  "candidate_type": "runner_up"
}
```

### Effect

- Reset chỉ các manual ranks cho 1 candidate_type cụ thể.

---

## Database schema (bảng `manual_tiebreaker_ranks`)

| Column | Kiểu | Mô tả |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `tournament_type_id` | BIGINT UNSIGNED FK → tournament_types | |
| `group_id` | BIGINT UNSIGNED FK → groups | |
| `team_id` | BIGINT UNSIGNED FK → teams | |
| `manual_rank` | TINYINT UNSIGNED | 1-based rank do BTC gán trong cụm đồng hạng |
| `candidate_type` | VARCHAR(32) NULL | `NULL` = intra-group, `runner_up` / `third_place` = cross-group |
| `set_by_user_id` | BIGINT UNSIGNED FK → users NULL | Audit: ai set |
| `set_at` | TIMESTAMP NULL | Audit: khi nào set |
| `created_at`, `updated_at` | TIMESTAMP | |

Indexes:
- `UNIQUE (tournament_type_id, group_id, candidate_type, team_id)` — mỗi team chỉ có 1 rank.
- `INDEX (tournament_type_id, group_id, candidate_type)` — tra cứu nhanh.

---

## Flow sử dụng (end-to-end)

1. BTC xem BXH → `GET /rank` → response có `need_draw_lots = true` + các team có `pending_tie = true`.
2. BTC mở Modal "Bốc thăm" → FE gọi `GET /pending-ties` để lấy danh sách cụm.
3. BTC kéo-thả sắp xếp thứ tự trong modal → FE gọi `POST /manual-tiebreaker` với rankings.
4. Modal đóng → FE gọi lại `GET /rank` → BXH cập nhật theo manual_rank.
5. Nếu BTC muốn hủy → `DELETE /manual-tiebreaker`.

---

## Lưu ý kỹ thuật

- **Idempotent**: gọi `POST /manual-tiebreaker` nhiều lần sẽ ghi đè (xóa cũ + insert mới).
- **Validate chặt**: API từ chối nếu team không thuộc cụm đồng hạng → tránh BTC set sai.
- **Không tự gắn `pending_draw = true`**: server vẫn trả `pending_draw = false` cho team đã có manual rank. UI dựa vào `pending_tie` (do BE tính) hoặc `manual_ranks` (do BE trả) để hiển thị.
- **Cross-group**: `candidate_type` là filter quan trọng. Mỗi Nhì chỉ có 1 manual rank (intra-group + cross-group độc lập).

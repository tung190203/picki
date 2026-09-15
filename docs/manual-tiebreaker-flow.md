# Manual Tiebreaker Flow — Luồng bốc thăm thủ công cho BTC

> **Audience**: Team Mobile (Android/iOS/Web) tích hợp với API backend.
> **Scope**: Tài liệu này mô tả luồng cho phép BTC xếp thứ hạng thủ công khi có đồng hạng (đặc biệt ở ranh giới đi tiếp/vòng loại), và cách các API `bracket`/`rank` phản ánh kết quả bốc thăm.

---

## 1. Bối cảnh (Context)

Trong vòng bảng (Pool Stage), các đội được xếp hạng tự động theo **ranking rules** (điểm → hiệu số → …). Tuy nhiên:

- Sau khi áp dụng **tất cả ranking rules thực sự** (trừ `HEAD_TO_HEAD` và `RANDOM_DRAW`), có thể vẫn còn **cụm đồng hạng** (≥2 đội cùng stats).
- Nếu cụm đồng hạng nằm **ở ranh giới đi tiếp** (ví dụ: vị trí số 2 & 3 trong bảng mà chỉ lấy top 2), BTC cần bốc thăm / kéo-thả thủ công để quyết định đội nào đi tiếp.
- Sau khi BTC bốc thăm, **bracket vòng sau phải được fill đúng** các đội đã được BTC chọn.

---

## 2. Các API liên quan

| Method | Endpoint | Vai trò |
|---|---|---|
| `GET` | `/api/tournament-types/{type}/groups/{group}/pending-ties` | Liệt kê các cụm đồng hạng **cần bốc thăm** trong 1 group |
| `POST` | `/api/tournament-types/{type}/groups/{group}/manual-tiebreaker` | Lưu thứ hạng thủ công cho 1 cụm đồng hạng |
| `DELETE` | `/api/tournament-types/{type}/groups/{group}/manual-tiebreaker` | Reset thứ hạng thủ công (xóa hết manual ranks của group) |
| `POST` | `/api/tournament-types/{type}/groups/{group}/manual-tiebreaker/cross` | Lưu manual ranks cho **cross-group candidates** (Nhì tốt nhất/Ba tốt nhất) |
| `DELETE` | `/api/tournament-types/{type}/groups/{group}/manual-tiebreaker/cross` | Reset cross-group manual ranks |
| `GET` | `/api/tournament-types/{type}/bracket` | Lấy toàn bộ bracket — **đã áp dụng manual** |
| `GET` | `/api/tournament-types/{type}/rank` | Lấy BXH — **đã áp dụng manual** |

> **Lưu ý cho Mobile**: Mobile **không cần tự tính** thứ hạng. Chỉ cần:
> 1. Gọi `.../pending-ties` để biết cụm nào cần bốc thăm.
> 2. Hiển thị UI kéo-thả / chọn rank cho BTC.
> 3. Gọi `POST .../manual-tiebreaker` để lưu.
> 4. Refresh `bracket` hoặc `rank` để cập nhật.

---

## 3. Luồng hoạt động (End-to-End Flow)

### Phase 1 — Phát hiện cụm đồng hạng

**Trigger**: Sau khi tất cả trận trong group hoàn thành, BTC mở trang BXH.

**API call**: `GET /api/tournament-types/{type}/groups/{group}/pending-ties`

**Response mẫu**:

```json
{
  "status": "success",
  "data": {
    "tournament_type_id": 269,
    "group_id": 694,
    "group_finished": true,
    "num_advancing": 2,
    "ranking_rules": [1, 2, 3, 4, 5, 6, 7],
    "existing_manual": {},
    "clusters": [
      {
        "indices": [0, 1, 2],
        "team_ids": [1516, 1518, 1519],
        "stats": {
          "points": 6,
          "win_rate": 66.67,
          "sets_diff": 0,
          "point_diff": 0,
          "points_for": 0
        },
        "teams": [
          { "team_id": 1516, "team_name": "Đội A" },
          { "team_id": 1518, "team_name": "Đội B" },
          { "team_id": 1519, "team_name": "Đội C" }
        ]
      }
    ]
  }
}
```

**Ý nghĩa**:
- Có **1 cụm đồng hạng** gồm 3 đội (1516, 1518, 1519) ở các vị trí rank 1, 2, 3.
- `num_advancing = 2` → chỉ top 2 đi tiếp.
- Cụm này **ảnh hưởng** đến ranh giới đi tiếp → **BẮT BUỘC bốc thăm**.

**Mobile UI gợi ý**:
- Hiển thị banner: "Có **3 đội đang đồng hạng** tại ranh giới đi tiếp. Vui lòng bốc thăm."
- Liệt kê 3 đội trong cụm, cho phép BTC kéo-thả để xếp thứ tự 1-2-3.

---

### Phase 2 — BTC lưu thứ hạng thủ công

**Trigger**: BTC đã kéo-thả xong, bấm "Lưu".

**API call**: `POST /api/tournament-types/{type}/groups/{group}/manual-tiebreaker`

**Request body**:

```json
{
  "rankings": [
    { "team_id": 1518, "manual_rank": 1 },
    { "team_id": 1516, "manual_rank": 2 },
    { "team_id": 1519, "manual_rank": 3 }
  ]
}
```

**Validation backend sẽ kiểm tra**:
1. Tất cả `team_id` phải **nằm trong cùng 1 cụm đồng hạng**.
2. `manual_rank` phải là số nguyên dương, không trùng nhau, không được vượt quá số team trong cụm.
3. Cụm được chọn phải **chứa tất cả team trong request** (subset cũng OK).

**Response mẫu (success)**:

```json
{
  "status": "success",
  "data": {
    "message": "Đã lưu thứ hạng thủ công",
    "group_id": 694,
    "tournament_type_id": 269
  }
}
```

**Error responses**:

| HTTP | Lý do | Message |
|---|---|---|
| 403 | BTC không có quyền chỉnh sửa BXH | `Bạn không có quyền chỉnh sửa BXH` |
| 404 | Không tìm thấy giải đấu | `Không tìm thấy giải đấu` |
| 422 | Không thuộc cùng cụm đồng hạng | `Các đội được chọn không nằm trong cùng một cụm đồng hạng hoặc chưa đồng hạng` |
| 422 | manual_rank trùng | `manual_rank phải là duy nhất` |

---

### Phase 3 — Backend tự động fill vào vòng sau

**Trigger tự động**: Ngay khi BTC gọi:
- `POST .../manual-tiebreaker` (intra-group) — backend gọi `applyPoolAdvancement()` + `resolveVirtualPoolAdvancementRules()` **bên trong transaction**.
- `POST .../manual-tiebreaker/cross` (cross-group) — tương tự.
- `DELETE .../manual-tiebreaker` hoặc `DELETE .../manual-tiebreaker/cross` — re-fill lại knockout với stats thường.
- Hoặc khi BTC **complete trận cuối cùng của pool** (gọi API complete match) — `MatchesController::checkAllPoolsCompleted` tự trigger.

**Logic tự động**:
1. `applyPoolAdvancement()`: Với mỗi group, áp dụng **manual ranks** trên standings → chọn Nhất/Nhì theo rank thực tế → fill `home_team_id` / `away_team_id` cho các trận **round 2** (knockout) dựa trên `PoolAdvancementRule` (real rules).
2. `resolveVirtualPoolAdvancementRules()`: Với các slot **ảo** (Nhì tốt nhất giữa các bảng), dùng `CrossGroupComparisonService` đã áp dụng manual ranks → fill vào các trận round 2 còn trống.

**Kết quả**: Sau khi backend xử lý, các trận vòng sau (round ≥ 2) sẽ có **đúng đội** BTC đã chọn thông qua bốc thăm.

**Lưu ý cho Mobile**:
- Mobile **không cần gọi** `applyPoolAdvancement` thủ công.
- Sau khi lưu manual, mobile chỉ cần **poll** hoặc **refresh** `GET /bracket` để thấy cập nhật.
- Nếu BTC đã hoàn thành pool và backend đã fill xong, trận round 2 sẽ có `home_team_id` / `away_team_id` ≠ null.

---

### Phase 4 — Hiển thị BXH & Bracket sau khi bốc thăm

#### `GET /api/tournament-types/{type}/rank`

Trả về danh sách BXH với các trường:

```json
{
  "data": [
    {
      "tournament_id": 1295,
      "groups": [
        {
          "group_id": 694,
          "group_name": "Bảng A",
          "need_draw_lots": false,
          "advanced_team_ids": [1518, 1516],
          "rankings": [
            {
              "team_id": 1518,
              "team_name": "Đội B",
              "rank": 1,
              "points": 6,
              "win_rate": 66.67,
              "pending_tie": false
            },
            {
              "team_id": 1516,
              "team_name": "Đội A",
              "rank": 2,
              "points": 6,
              "win_rate": 66.67,
              "pending_tie": false
            },
            {
              "team_id": 1519,
              "team_name": "Đội C",
              "rank": 3,
              "points": 6,
              "win_rate": 66.67,
              "pending_tie": false
            }
          ]
        }
      ]
    }
  ]
}
```

**Các trường quan trọng**:

| Field | Ý nghĩa |
|---|---|
| `rank` | Thứ hạng **sau khi áp dụng manual ranks** (nếu có). |
| `pending_tie` | `true` nếu team này đang chờ bốc thăm (chưa có manual ranks). |
| `need_draw_lots` | `true` nếu group có cụm đồng hạng cần bốc thăm. |
| `advanced_team_ids` | Danh sách team_id **đã xác định rõ đi tiếp** (loại trừ team chờ bốc thăm). |

#### `GET /api/tournament-types/{type}/bracket`

Trả về structure đầy đủ gồm `poolStage` (BXH + trận vòng bảng) và `knockoutStage` (các vòng sau pool):

```json
{
  "data": {
    "poolStage": [
      {
        "group_id": 694,
        "group_name": "Bảng A",
        "standings": [ /* Đã áp dụng manual ranks */ ],
        "matches": [ /* Trận vòng bảng */ ]
      }
    ],
    "knockoutStage": [
      {
        "round": 2,
        "round_name": "Tứ kết",
        "matches": [
          {
            "match_id": 12345,
            "home_team": { "id": 1518, "name": "Đội B" },
            "away_team": { "id": 1520, "name": "Đội D" },
            "legs": [ /* Leg 1, Leg 2 */ ]
          }
        ]
      }
    ]
  }
}
```

**Đặc biệt**:
- `poolStage[].standings`: **đã được sort lại theo manual ranks** (nếu BTC đã bốc thăm).
- `knockoutStage[].matches[].home_team` / `away_team`: **đã được fill** đúng team BTC đã chọn.

---

## 4. Quy tắc quan trọng cho Mobile

### 4.1. Khi nào cần hiển thị "Bốc thăm" UI?

Hiển thị nút "Bốc thăm" hoặc banner nếu **`pending` API trả về ít nhất 1 cluster** trong group đó.

### 4.2. Khi nào cần Refresh Bracket?

Sau khi gọi `POST .../manual-tiebreaker` thành công:
- Refresh cả `bracket` lẫn `rank` (vì 2 API này có thể dùng cho 2 view khác nhau).
- Có thể dùng polling 3-5 giây/lần, hoặc refetch on-screen-focus.

### 4.3. Cross-group Manual Ranks

- Nếu giải có **nhiều bảng** và **cross_group_ranking** được bật, BTC có thể cần bốc thăm **Nhì tốt nhất** (rank 2 giữa các bảng) hoặc **Ba tốt nhất** (rank 3).
- Dùng endpoint `.../manual-tiebreaker/cross` với thêm field `candidate_type` (`runner_up` | `third_place`).
- Backend sẽ tự động áp dụng các manual ranks này khi fill vào slot ảo trong knockout.

### 4.4. Reset Manual Ranks

Nếu BTC muốn bỏ bốc thăm (cho nhập lại):
- `DELETE .../manual-tiebreaker` → xóa hết manual ranks (cả intra-group lẫn cross-group).
- `DELETE .../manual-tiebreaker/cross` → chỉ xóa cross-group (giữ intra-group).
- Sau khi reset, **knockout round 2 phải được fill lại**. Backend sẽ tự động chạy lại khi:
  - BTC complete thêm trận pool nào đó, HOẶC
  - BTC gọi `POST .../rebuild-knockout-pairing` (xem docs riêng).

---

## 5. Ví dụ End-to-End

### Scenario: 2 bảng × 4 đội, top 2 đi tiếp mỗi bảng, có đồng hạng

```
Bảng A:        Bảng B:
1. Team X      1. Team P (5đ)
2. Team Y (5đ) 2. Team Q (5đ)
3. Team Z (5đ) 3. Team R (3đ)
4. Team W (1đ)  4. Team S (1đ)

Nhất + Nhì mỗi bảng đi tiếp + 2 Nhì tốt nhất (cross-group).
```

**Bước 1**: Pool kết thúc → `GET /api/tournament-types/{type}/groups/A/pending-ties`

```json
{
  "clusters": [
    {
      "indices": [1, 2],
      "team_ids": [/* Team Y */, /* Team Z */],
      "stats": { "points": 5, "win_rate": 66.67, "sets_diff": 0 }
    }
  ]
}
```

**Bước 2**: BTC kéo-thả: Y (rank 2) > Z (rank 3).

**Bước 3**: `POST .../manual-tiebreaker`

```json
{
  "rankings": [
    { "team_id": /* Y */, "manual_rank": 1 },
    { "team_id": /* Z */, "manual_rank": 2 }
  ]
}
```

**Bước 4**: BTC complete trận pool cuối → backend tự động fill round 2 với:
- Nhất bảng A = X, Nhì bảng A = Y (do manual)
- Nhất bảng B = P, Nhì bảng B = Q (auto)
- Cross-group candidates: Y vs Q (nếu cần bốc thêm)

**Bước 5**: Mobile refresh `GET .../bracket` → thấy round 2 đã có đội.

---

## 6. Lưu ý kỹ thuật cho Mobile

### 6.1. Polling vs WebSocket

Hiện tại backend **không push realtime**. Mobile cần:
- **Polling** `/bracket` mỗi 5-10s khi user đang ở trang BXH/Bracket, HOẶC
- Refetch on-screen-focus (mỗi lần user quay lại tab).

Nếu cần realtime, đề xuất dùng Laravel Reverb / Pusher (chưa có sẵn — cần làm thêm).

### 6.2. Error Handling

| Mã lỗi | Xử lý |
|---|---|
| 401 | Logout → quay về màn hình đăng nhập |
| 403 | Hiển thị "Bạn không có quyền" |
| 404 | Hiển thị "Giải đấu không tồn tại" |
| 422 | Hiển thị message backend trả về (đã được localize tiếng Việt) |
| 500 | Refetch sau 5s + hiển thị "Lỗi hệ thống, đang thử lại..." |

### 6.3. Concurrency

Nếu 2 BTC cùng bốc thăm cùng lúc:
- Backend sẽ ghi đè (`updateOrCreate`) → không lỗi nhưng thao tác sau thắng.
- Mobile nên **disable UI** sau khi submit cho tới khi nhận response.

---

## 7. Sequence Diagram

```
┌────────┐         ┌──────────┐         ┌──────────────┐
│ Mobile │         │ Backend  │         │   Database   │
└───┬────┘         └────┬─────┘         └──────┬───────┘
    │  GET pending      │                       │
    │───────────────────>                       │
    │                   │   query standings      │
    │                   │──────────────────────>│
    │                   │<──────────────────────│
    │   clusters + UI   │                       │
    │<──────────────────│                       │
    │                   │                       │
    │  POST manual-ranks│                       │
    │───────────────────>                       │
    │                   │   validate cluster    │
    │                   │──────────────────────>│
    │                   │<──────────────────────│
    │                   │   save manual_ranks   │
    │                   │──────────────────────>│
    │   success         │                       │
    │<──────────────────│                       │
    │                   │                       │
    │  (later) complete │                       │
    │  pool match       │                       │
    │───────────────────>                       │
    │                   │   applyPoolAdvancement│
    │                   │   (apply manual ranks)│
    │                   │──────────────────────>│
    │                   │   fill round 2 teams  │
    │                   │──────────────────────>│
    │                   │                       │
    │  GET bracket      │                       │
    │───────────────────>                       │
    │                   │   query with manual   │
    │                   │──────────────────────>│
    │   bracket (filled)│                       │
    │<──────────────────│                       │
    │                   │                       │
```

---

## 8. Tóm tắt nhanh cho Mobile

| Khi nào | Gọi API | Mục đích |
|---|---|---|
| Mở trang BXH | `GET .../pending-ties` | Biết cụm nào cần bốc thăm |
| BTC bấm "Lưu" | `POST .../manual-tiebreaker` | Lưu thứ hạng thủ công |
| Sau khi lưu / hoàn thành pool | `GET .../bracket` + `GET .../rank` | Lấy bracket + BXH đã cập nhật |
| BTC muốn reset | `DELETE .../manual-tiebreaker` | Xóa manual ranks |

**Không cần**: Gọi `applyPoolAdvancement` thủ công — backend tự động khi pool hoàn thành.

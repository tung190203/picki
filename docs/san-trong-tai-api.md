# Tài liệu API: Sân & Trọng tài trong Giải đấu

## 1. Quy tắc nghiệp vụ cốt lõi

1. **Vai trò (`tournament_staff`)**:
   - **Admin (`ROLE_ORGANIZER = 1`) / BTC (`ROLE_STAFF = 2`)**: Khai báo sân, gán trọng tài, chia bảng/trận vào sân, chuyển trận chờ, đổi trọng tài trận đang đấu.
   - **Trọng tài (`ROLE_REFEREE = 3`)**: Được gán vào sân (0 hoặc 1 trọng tài/sân; 1 trọng tài có thể phụ trách nhiều sân). Bắt trận ở sân mình, kéo trận chờ từ sân khác hoặc chưa xếp sân về sân mình.
   - **User thường**: Chỉ dùng được **Tạo trận mới (ngoài giải)**.
2. **Hàng đợi trận của sân**:
   - Thứ tự: `priority_at` (khác `null` lên trước, `ASC`) → `scheduled_at ASC` → `id ASC`.
   - `is_next = true`: trận chờ đầu tiên trong hàng đợi của sân không có VĐV nào đang bận.
3. **VĐV đang bận (`busy`)**:
   - Kiểm tra trên toàn bộ trận đang live (`live_status` ∈ `playing | timeout | between_sets`) ở mọi giải.
   - So khớp theo `user_id` (VĐV có tài khoản) hoặc `participant_id` / `guest_member_id` (khách).
4. **Khoá trận & Khoá nhập điểm**:
   - Khi trọng tài bấm bắt trận (`claim`), server dùng transaction + `FOR UPDATE` để chuyển `live_status = playing`, gán `referee_id = user.id`, `started_at = now()`.
   - Khi trận đang live và đã có `referee_id`, chỉ đúng `referee_id` đó mới được gửi điểm (`/matches/{id}/score/start`, `/matches/{id}/score/update`, `/matches/{id}/stages`, `/matches/confirm-result/{id}`). Người khác nhận `403` với `errors.error_code = "NOT_MATCH_REFEREE"`.

---

## 2. Database Schema

- **`tournament_courts`**: `id`, `tournament_id`, `name` (`varchar(40)`, unique trong giải với sân chưa xoá), `sort_order`, `referee_user_id` (nullable), `deleted_at` (soft delete để trận đã xong giữ tên sân).
- **`matches`** (bổ sung): `court_id` (nullable FK → `tournament_courts.id`), `priority_at` (nullable timestamp), `finished_at` (nullable timestamp), tái sử dụng `referee_id`.
- **`court_assignments`**: `id`, `tournament_id`, `unit_type` (`group | slot`), `group_id`, `slot_round` (`QF | SF | P3 | F | R16 | ...`), `slot_no`, `court_id`, `sort_order`.
- **`free_matches`**: `id`, `creator_user_id`, `title`, `rules` (json), `team_a` (json), `team_b` (json), `score` (json), `status` (`playing | done`).

---

## 3. Phần A – Quản lý Sân & Bảng Sân (Admin / BTC)

### 3.1 Danh sách sân & trọng tài của giải
- **Method & URL**: `GET /api/tournaments/{id}/courts`
- **Quyền**: Admin / BTC của giải.
- **Response `200`**:
```json
{
  "status": true,
  "message": "Success",
  "data": {
    "courts": [
      {
        "id": 12,
        "tournament_id": 1,
        "name": "Sân 1",
        "order": 1,
        "referee": { "user_id": 88, "name": "Bảo Quốc", "avatar_url": "...", "initials": "BQ" }
      }
    ],
    "referees": [
      { "user_id": 88, "name": "Bảo Quốc", "avatar_url": "...", "initials": "BQ", "is_me": false, "court_ids": [12] }
    ]
  }
}
```

### 3.2 Thêm sân
- **Method & URL**: `POST /api/tournaments/{id}/courts`
- **Body**: `{ "name": "Sân 5" }` (`name` không bắt buộc; bỏ trống sẽ tự sinh `"Sân 1"`, `"Sân 2"`, ...).
- **Response `201`**: Trả về object sân vừa tạo. Trùng tên trong giải trả `422`.

### 3.3 Cập nhật sân / Gán trọng tài cho sân
- **Method & URL**: `PATCH /api/courts/{id}`
- **Body**: `{ "name"?: string, "referee_user_id"?: int|null, "sort_order"?: int }`
  - Truyền `"referee_user_id": null` để bỏ gán trọng tài.
  - Khi đổi trọng tài của sân: các trận đang chờ (`pending` + `waiting`) của sân tự chuyển `referee_id` sang trọng tài mới; trận đang đấu giữ nguyên người bắt hiện tại.
  - Gửi FCM cho trọng tài cũ (bị bỏ gán) và trọng tài mới (được gán).

### 3.4 Xoá sân
- **Method & URL**: `DELETE /api/courts/{id}`
- **Lỗi `409`**: `{ "status": false, "message": "...", "errors": { "error_code": "COURT_HAS_LIVE_MATCH" } }` nếu sân đang có trận live.
- **Thành công `200`**: Soft-delete sân, gỡ `court_assignments` của sân đó, đưa các trận chưa đấu về chưa xếp sân (`court_id = null`), trận đã xong vẫn giữ tên sân cũ.

### 3.5 Sao chép sân & trọng tài từ giải khác
- **Method & URL**: `POST /api/tournaments/{id}/courts/copy`
- **Quyền**: Admin / BTC ở cả 2 giải.
- **Body**: `{ "from_tournament_id": 10 }`
- **Lưu ý**: Chỉ giữ `referee_user_id` nếu người đó cũng có vai trò Trọng tài ở giải đích. Trả `409 COURT_HAS_LIVE_MATCH` nếu giải đích đang có trận live.

### 3.6 Lấy danh sách bảng & trận để kéo thả vào sân
- **Method & URL**: `GET /api/tournaments/{id}/matches`
- **Ghi chú**: Trả thẳng mảng các bảng đấu & slot loại trực tiếp (`courts` và `referees` lấy từ `GET /api/tournaments/{id}/courts`).
- **Response `200`**:
```json
{
  "status": true,
  "data": [
    {
      "type": "group",
      "group_id": 5,
      "label": "Bảng A",
      "match_count": 6,
      "courts": [{ "court_id": 12, "count": 3 }, { "court_id": 15, "count": 3 }],
      "matches": [
        {
          "id": 101,
          "label": "Đội A1 vs Đội A2",
          "team_a": "Đội A1",
          "team_b": "Đội A2",
          "court_id": 12,
          "referee_user_id": 88,
          "scheduled_at": "09:00",
          "status": "pending"
        }
      ]
    },
    {
      "type": "slot",
      "round": "QF",
      "no": 3,
      "label": "Tứ kết 3",
      "match_id": 301,
      "court_id": null,
      "referee_user_id": null
    }
  ]
}
```

### 3.7 Lưu phân sân cho 1 đơn vị (Bảng hoặc Slot loại trực tiếp)
- **Method & URL**: `PUT /api/tournaments/{id}/court-board/units`
- **Body**:
  - Cho vòng bảng: `{ "unit_type": "group", "group_id": 5, "court_ids": [12, 15] }`
  - Cho loại trực tiếp: `{ "unit_type": "slot", "slot_round": "QF", "slot_no": 1, "court_ids": [12] }`
  - `court_ids: []` = bỏ xếp sân (đưa trận chờ về chưa xếp sân).
- **Hành vi**: Chia lần lượt các trận chưa đấu của bảng theo `scheduled_at ASC, id ASC` vào các sân trong `court_ids`. Khi trận loại trực tiếp mới được sinh sau vòng bảng, hệ thống tự áp dụng `court_assignments` theo `slot_round` + `slot_no`.

### 3.8 Lưu toàn bộ bảng sân (nút "Xác nhận")
- **Method & URL**: `PUT /api/tournaments/{id}/court-board`
- **Quyền**: Admin / BTC của giải.
- **Body**:
```json
{
  "courts": [
    {
      "court_id": 12,
      "number": 1,
      "name": "Sân 1",
      "referee_user_id": 88,
      "match_ids": [101, 102, 301],
      "slots": [{ "round": "QF", "no": 1 }]
    },
    {
      "court_id": null,
      "number": 2,
      "name": "Sân 2",
      "referee_user_id": null,
      "match_ids": [],
      "slots": []
    }
  ]
}
```
- **Hành vi**: Tạo/cập nhật/xoá sân (soft-delete sân không còn trong danh sách, chặn `409 COURT_HAS_LIVE_MATCH` nếu sân bị xoá đang có trận live), gán trọng tài và phân trận chờ (`match_ids`) + `slots` loại trực tiếp trong 1 transaction. Trả về `{ courts, matches }`.

### 3.9 Chia sân tự động
- **Method & URL**: `POST /api/tournaments/{id}/court-board/auto`
- **Body (tuỳ chọn)**:
```json
{
  "courts": [
    { "court_id": 12, "number": 1, "name": "Sân 1" },
    { "court_id": null, "number": 2, "name": "Sân 2" }
  ]
}
```
- **Hành vi**: Khi truyền `courts`, server tự động phân trọng tài (xoay vòng trong danh sách trọng tài của giải) và các trận chờ/slot vào từng sân rồi trả về `data.courts` (gồm `court_id`, `number`, `name`, `referee_user_id`, `referee`, `match_ids`, `slots`) để app hiển thị lên UI trước khi bấm **Xác nhận** (`PUT /court-board`). Nếu không truyền `body`, tự động chia và lưu luôn các sân hiện có.

---

## 4. Phần B – Trọng tài & Điều phối trận

### 4.1 Danh sách giải đang & sắp diễn ra của tôi (Bước 1)
- **Method & URL**: `GET /api/referee/tournaments`
- **Quyền**: User đã đăng nhập. Trả về các giải đang mở (`status = OPEN`, chưa qua `end_date`) — bao gồm cả giải đang diễn ra và giải sắp diễn ra — mà user có mặt trong `tournament_staff`. App kiểm tra `start_date` để phân biệt giải sắp diễn ra.
- **Response `200`**:
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "Picki Cup · Đôi nam",
      "short": "Picki Cup · Đôi nam",
      "poster_url": "...",
      "location": "Sân ABC",
      "start_date": "2026-11-23T08:00:00Z",
      "end_date": "2026-11-25T20:00:00Z",
      "roles": ["Admin", "Trọng tài"],
      "my_courts": [{ "id": 12, "name": "Sân 2", "order": 2 }],
      "waiting_count": 3
    }
  ]
}
```

### 4.2 Danh sách trận gộp nhiều giải (Bước 2)
- **Method & URL**: `GET /api/referee/matches?tournament_ids=1,2`
- **Quyền**: Thành viên BTC của tất cả các giải trong `tournament_ids`.
- **Response `200`**:
```json
{
  "status": true,
  "data": {
    "tournaments": [
      { "id": 1, "short": "Picki Cup · Đôi nam", "color": "c1", "roles": ["Admin", "Trọng tài"], "can_move": true }
    ],
    "courts": [
      { "name": "Sân 2", "ids": { "1": 12, "2": 31 }, "mine": true, "referee": "Thang Tagz", "order": 2 }
    ],
    "matches": [
      {
        "id": 3,
        "tournament_id": 1,
        "court_id": 12,
        "court_name": "Sân 2",
        "scheduled_at": "14:00",
        "scheduled_at_iso": "2026-11-23T14:00:00+07:00",
        "priority_at": null,
        "round": "Bảng B · Lượt 2",
        "team_a": "Trương Quý - Xuân Trường",
        "team_b": "Blucati - Duy Nguyễn",
        "status": "waiting",
        "score": [0, 0],
        "referee": null,
        "court_referee": "Thang Tagz",
        "busy": { "player": "Ngọc HPT", "court_name": "Sân 1" },
        "is_next": false,
        "can_claim": false,
        "can_move": true
      }
    ]
  }
}
```
- **Ghi chú**: `status` ∈ `waiting | live | done` (nhóm `done` chỉ lấy trận hoàn thành trong ngày).

### 4.3 Bắt trận (Claim)
- **Method & URL**: `POST /api/matches/{id}/claim`
- **Quyền**: Trọng tài phụ trách sân của trận đó.
- **Mã lỗi**:
  - `409 ALREADY_CLAIMED`: Trận không còn ở trạng thái chờ (đã có người bắt).
  - `403 NOT_YOUR_COURT`: User không phải trọng tài của sân trận.
  - `409 PLAYER_BUSY`: Có VĐV đang thi đấu ở trận live khác.

### 4.4 Chuyển trận / Đưa lên đầu hàng / Kéo trận về sân mình
- **Method & URL**: `POST /api/matches/{id}/move`
- **Quyền**: Admin/BTC của giải, **hoặc** trọng tài của sân đích (`court_id` khác `null`).
- **Body**: `{ "court_id": 12 | null, "to_top": true | false }`
  - `court_id: null` → đưa trận về nhóm Chưa xếp sân (chỉ Admin/BTC).
  - `to_top: true` → gán `priority_at = now()` để đưa lên đầu hàng chờ.

### 4.5 Đổi trọng tài trận đang đấu
- **Method & URL**: `POST /api/matches/{id}/reassign`
- **Quyền**: Admin / BTC của giải.
- **Body**: `{ "referee_user_id": 99 }` (phải có vai trò Trọng tài trong BTC của giải).
- **Hành vi**: Chuyển `referee_id` sang người mới, giữ nguyên tỉ số & lượt giao; người cũ gửi điểm tiếp theo sẽ bị chặn `403 NOT_MATCH_REFEREE`.

---

## 5. Trận ngoài giải (Free Matches)

- **Danh sách trận của tôi**: `GET /api/free-matches?per_page=20`
- **Tạo trận ngoài giải**: `POST /api/free-matches`
  ```json
  {
    "title": "Trận giao hữu",
    "rules": { "points_to_win": 11, "win_by": 2, "sets": 1 },
    "team_a": [{ "user_id": 10, "name": "Nguyễn A" }, { "name": "Khách B" }],
    "team_b": [{ "name": "Trần C" }, { "name": "Lê D" }]
  }
  ```
- **Cập nhật tỉ số / kết thúc**: `PATCH /api/free-matches/{id}`
  ```json
  {
    "score": { "sets": [[11, 8]] },
    "status": "done"
  }
  ```

---

## 6. Thông báo đẩy (FCM)

Thông báo gửi qua `SendPushJob` (loại trừ chính người thực hiện thao tác) với `data.type`:
- `referee_queue_updated`: gán/bỏ gán sân, chuyển trận giữa các sân, đưa trận lên đầu hàng, cập nhật bảng sân.
- `referee_match_reassigned`: đổi trọng tài trận đang đấu (`data.match_id`, `data.new_referee_user_id`).

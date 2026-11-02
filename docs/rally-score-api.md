# Rally Score API Documentation

Tài liệu hướng dẫn tích hợp API cho hệ thống tính điểm **Rally Score theo chặng (Stages)** dành cho các nền tảng Web, Mobile (Flutter/React Native) và bên thứ ba.

---

## 1. Tổng Quan Về Cơ Chế Rally Score

- **Điều kiện kích hoạt:** Giải đấu có `player_per_team >= 3` (3 người hoặc 4 người/đội) → Backend tự động suy luận `scoring_type = "rally"`. Nếu `player_per_team < 3` → `scoring_type = "side_out"` (truyền thống).
- **Mốc điểm chặng (`stage_points`):** Là mảng số nguyên tăng dần (ví dụ: `[10, 20, 30]`). Trận đấu sẽ thi đấu qua số chặng tương ứng với số mốc (2 đến 5 chặng, khoảng cách giữa 2 mốc ≥ 5 điểm).
- **Điểm chặng:**
  - `cumulative_score_a`, `cumulative_score_b`: Điểm lũy kế thực tế tại chặng đó.
  - `stage_score_a`, `stage_score_b`: Điểm riêng ghi được trong chặng đó (backend tự động tính bằng hiệu số với chặng trước).
- **Thành viên ra sân theo chặng (`match_stage_members`):** Mỗi chặng có thể chọn cặp vận động viên (1 hoặc 2 người/đội) khác nhau trong danh sách thành viên đội.
- **Xác nhận trận đấu:** Khi **tất cả** các chặng đều đã chạm mốc điểm (`cumulative_score >= target_point`), hệ thống mới cho phép gọi API xác nhận kết quả. Đội thắng chung cuộc là đội có điểm cao hơn ở chặng cuối cùng.

---

## 2. Base URL & Authentication

- **Base URL:** `/api`
- **Headers yêu cầu:**
  ```http
  Authorization: Bearer {token}
  Content-Type: application/json
  Accept: application/json
  ```

---

## 3. Danh Sách Endpoints

### 3.1. Cấu hình giải đấu & Thể thức

#### A. Cập nhật mốc điểm chặng thể thức
Thiết lập mốc điểm cho thể thức giải đấu.

- **Method:** `PUT` / `POST`
- **URL:** `/api/tournament-types/{id}`
- **Headers:** `Authorization: Bearer {token}`
- **Request Body:**
  ```json
  {
    "match_rules": {
      "stage_points": [10, 20, 30],
      "sets_per_match": 3,
      "winning_rule": 2
    }
  }
  ```

| Trường | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `match_rules.stage_points` | `array<int>` | Có (nếu Rally) | Mảng 2 đến 5 số nguyên dương tăng nghiêm ngặt, cách nhau ≥ 5 điểm. Ví dụ: `[10, 20, 30]`. |

- **Response thành công (200 OK):**
  ```json
  {
    "success": true,
    "message": "Cập nhật thành công",
    "data": {
      "id": 1,
      "name": "Vòng Bảng Rally 4v4",
      "scoring_type": "rally",
      "stage_points": [10, 20, 30],
      "match_rules": {
        "scoring_type": "rally",
        "stage_points": [10, 20, 30]
      }
    }
  }
  ```

---

### 3.2. Quản lý điểm chặng trận đấu (Match Stages)

#### A. Lưu / Cập nhật điểm các chặng (Save Stages)
Ghi nhận điểm số và danh sách VĐV ra sân cho từng chặng của trận đấu. API này có tính chất **Idempotent** theo `(match_id, stage_no)`.

- **Method:** `POST`
- **URL:** `/api/matches/{matchId}/stages`
- **Headers:** `Authorization: Bearer {token}` (User phải có quyền ghi điểm trận: BTC, trọng tài, hoặc VĐV của trận).
- **Request Body:**
  ```json
  {
    "stages": [
      {
        "stage_no": 1,
        "cumulative_score_a": 10,
        "cumulative_score_b": 6,
        "members": [
          { "user_id": 101, "team_id": 1 },
          { "user_id": 102, "team_id": 1 },
          { "user_id": 201, "team_id": 2 },
          { "user_id": 202, "team_id": 2 }
        ]
      },
      {
        "stage_no": 2,
        "cumulative_score_a": 20,
        "cumulative_score_b": 15,
        "members": [
          { "user_id": 103, "team_id": 1 },
          { "user_id": 104, "team_id": 1 },
          { "user_id": 203, "team_id": 2 },
          { "user_id": 204, "team_id": 2 }
        ]
      }
    ]
  }
  ```

| Trường | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `stages` | `array` | Có | Danh sách chặng cần lưu (tối thiểu 1 chặng, bắt đầu từ `stage_no = 1`). |
| `stages.*.stage_no` | `integer` | Có | Thứ tự chặng (1..5). |
| `stages.*.cumulative_score_a` | `integer` | Có | Điểm lũy kế của Đội nhà (Home Team). |
| `stages.*.cumulative_score_b` | `integer` | Có | Điểm lũy kế của Đội khách (Away Team). |
| `stages.*.members` | `array` | Không | Danh sách VĐV tham gia chặng đó. |
| `stages.*.members.*.user_id` | `integer` | Có (nếu có members) | ID vận động viên. Phải thuộc đúng `team_id`. |
| `stages.*.members.*.team_id` | `integer` | Có (nếu có members) | ID của đội mà vận động viên đại diện trong chặng. |

- **Quy tắc nghiệp vụ của API:**
  1. Chặng sau (n+1) chỉ được phép ghi điểm khi chặng trước (n) đã chạm mốc `target_point`.
  2. Cả 2 đội không được phép cùng lúc chạm mốc điểm tối đa của chặng.
  3. Khi một đội chạm mốc `target_point`, chặng tự động chuyển trạng thái `status = "completed"` và xác định `winner_team_id`.
  4. Backend tự động tính toán điểm rating VNDUPR (ELO) cho các VĐV tham gia chặng vừa hoàn thành.
  5. Đồng thời mirror dữ liệu sang `MatchResult` để tương thích bảng xếp hạng (standings) hiện có.

- **Response thành công (200 OK):**
  ```json
  {
    "success": true,
    "message": "Lưu stage thành công",
    "data": {
      "match_id": 452,
      "status": "in_progress",
      "winner_id": null,
      "stages": [
        {
          "stage_no": 1,
          "cumulative_score_a": 10,
          "cumulative_score_b": 6,
          "stage_score_a": 10,
          "stage_score_b": 6,
          "status": "completed",
          "winner_team_id": 1,
          "target_point": 10
        },
        {
          "stage_no": 2,
          "cumulative_score_a": 20,
          "cumulative_score_b": 15,
          "stage_score_a": 10,
          "stage_score_b": 9,
          "status": "completed",
          "winner_team_id": 1,
          "target_point": 20
        }
      ]
    }
  }
  ```

- **Mã lỗi thường gặp:**
  - `403 Forbidden`: Người dùng không có quyền ghi điểm trận đấu này.
  - `422 Unprocessable Entity`: Dữ liệu không hợp lệ (ví dụ: gửi chặng 2 khi chặng 1 chưa chạm mốc, điểm vượt mốc, hoặc VĐV không thuộc đội).
  - `400 Bad Request`: Trận đấu đã hoàn thành, không thể cập nhật thêm chặng.

---

#### B. Lấy danh sách điểm chặng trận đấu (Get Stages)
Đọc dữ liệu chi tiết các chặng đã lưu kèm thông tin thành viên ra sân.

- **Method:** `GET`
- **URL:** `/api/matches/{matchId}/stages`
- **Headers:** `Authorization: Bearer {token}`
- **Response thành công (200 OK):**
  ```json
  {
    "success": true,
    "message": "Lấy stages thành công",
    "data": {
      "match_id": 452,
      "status": "in_progress",
      "winner_id": null,
      "stages": [
        {
          "stage_no": 1,
          "cumulative_score_a": 10,
          "cumulative_score_b": 6,
          "stage_score_a": 10,
          "stage_score_b": 6,
          "status": "completed",
          "winner_team_id": 1,
          "target_point": 10,
          "completed_at": "2026-11-02T10:15:00+07:00",
          "members": {
            "team_a": [
              {
                "user_id": 101,
                "team_id": 1,
                "role": "player",
                "name": "Nguyễn Văn A"
              },
              {
                "user_id": 102,
                "team_id": 1,
                "role": "player",
                "name": "Trần Văn B"
              }
            ],
            "team_b": [
              {
                "user_id": 201,
                "team_id": 2,
                "role": "player",
                "name": "Lê Văn C"
              },
              {
                "user_id": 202,
                "team_id": 2,
                "role": "player",
                "name": "Phạm Văn D"
              }
            ]
          }
        }
      ]
    }
  }
  ```

---

### 3.3. Xác nhận kết quả trận đấu (Confirm Result)

Xác nhận kết thúc trận đấu và chốt tỉ số chung cuộc. API này tự động nhận diện nếu trận đấu là Rally Score để kích hoạt luồng `confirmRallyResult`.

- **Method:** `POST`
- **URL:** `/api/matches/confirm-result/{matchId}`
- **Headers:** `Authorization: Bearer {token}`
- **Request Body:** Không có (hoặc `{}`)

- **Điều kiện để xác nhận thành công:**
  1. Trận đấu phải có đủ số chặng theo cấu hình (`count($stages) === count($expectedStagePoints)`).
  2. **Tất cả các chặng đều phải ở trạng thái hoàn thành** (`status = "completed"`).
  3. Chặng cuối cùng không được phép có điểm hòa giữa 2 đội.

- **Xử lý bên dưới của Backend:**
  - Quyết định đội thắng chung cuộc (`winner_id`) dựa trên đội có `cumulative_score` cao hơn ở **chặng cuối cùng**.
  - Cập nhật `matches.status = "completed"`.
  - Cập nhật cờ xác nhận `home_team_confirm = true`, `away_team_confirm = true`.
  - Cập nhật bảng xếp hạng giải đấu (Standings/Leaderboard).
  - Bắn sự kiện realtime WebSocket đến các client đang theo dõi.

- **Response thành công (200 OK):**
  ```json
  {
    "success": true,
    "message": "Xác nhận kết quả rally thành công",
    "data": {
      "id": 452,
      "status": "completed",
      "winner_id": 1,
      "home_team_confirm": true,
      "away_team_confirm": true
    }
  }
  ```

- **Mã lỗi thường gặp:**
  - `400 Bad Request`:
    - `"Trận rally chưa có dữ liệu chặng nào"`
    - `"Chưa đủ chặng để xác nhận: cần 3 chặng, hiện có 2"`
    - `"Chặng 2 chưa hoàn thành (chưa đạt mốc)"`
    - `"Hai đội bằng điểm tổng kết, không thể xác nhận"`

---

### 3.4. Chi tiết trận đấu trong danh sách (`MatchDetailResource`)

Khi gọi API lấy danh sách trận đấu hoặc chi tiết trận đấu (`GET /api/tournaments/{tournamentId}/matches` hoặc `GET /api/matches/{matchId}`):
- Nếu trận đấu là Rally Score, trường `rally_stages` sẽ được trả về trực tiếp trong object trận đấu:

```json
{
  "id": 452,
  "round": 1,
  "status": "in_progress",
  "winner_id": null,
  "home_team": { "id": 1, "name": "Đội số 1" },
  "away_team": { "id": 2, "name": "Đội số 4" },
  "rally_stages": [
    {
      "stage_no": 1,
      "cumulative_score_a": 10,
      "cumulative_score_b": 6,
      "stage_score_a": 10,
      "stage_score_b": 6,
      "status": "completed",
      "winner_team_id": 1,
      "target_point": 10
    },
    {
      "stage_no": 2,
      "cumulative_score_a": 15,
      "cumulative_score_b": 12,
      "stage_score_a": 5,
      "stage_score_b": 6,
      "status": "pending",
      "winner_team_id": null,
      "target_point": 20
    }
  ]
}
```

---

## 4. Sự Kiện Realtime (WebSockets / Pusher / Laravel Echo)

Khi điểm số chặng được cập nhật hoặc trận đấu được xác nhận, hệ thống broadcast các sự kiện sau:

### 1. `TournamentMatchUpdated`
- **Channel:** `tournament.{tournamentId}`
- **Event:** `.TournamentMatchUpdated` (hoặc `App\Events\SuperAdmin\TournamentMatchUpdated`)
- **Payload:** Toàn bộ thông tin cập nhật của trận đấu kèm danh sách `stages.members.user`. Dùng để cập nhật sơ đồ nhánh đấu (bracket) và lịch thi đấu.

### 2. `MatchScorePublicUpdated`
- **Channel:** `public-match.{matchId}`
- **Event:** `.MatchScorePublicUpdated`
- **Payload:** Chứa thông tin điểm trận đấu và quan hệ `results`. Dùng cho màn hình Live Score công khai.

---

## 5. Luồng Tích Hợp Đề Xuất Cho Client (Web / Mobile)

```
[Bắt đầu trận Rally]
       │
       ▼
1. Nhận diện thể thức:
   - Kiểm tra: `tournament.player_per_team >= 3` hoặc `tournament_type.scoring_type === 'rally'`
   - Lấy mốc chặng: `stage_points = [10, 20, 30]`
       │
       ▼
2. Hiển thị giao diện:
   - Khởi tạo danh sách chặng theo số lượng `stage_points`.
   - Mỗi chặng cho phép chọn VĐV (1-2 người mỗi đội) từ danh sách thành viên đội.
   - Hiển thị bộ đếm điểm: `[-] [Điểm] [+]` cho từng đội.
       │
       ▼
3. Ghi điểm từng chặng:
   - Khi người dùng điều chỉnh điểm hoặc chọn VĐV:
   - Gọi: `POST /api/matches/{matchId}/stages`
   - Chỉ cho phép chỉnh sửa chặng n+1 khi chặng n đã đạt mốc (`status === "completed"`).
       │
       ▼
4. Kiểm tra điều kiện xác nhận:
   - Điều kiện nút "Xác nhận kết quả" sáng lên:
     Tất cả các chặng đều có ít nhất một đội đạt mốc `target_point`.
       │
       ▼
5. Kết thúc trận đấu:
   - Bước 5a: Gọi `POST /api/matches/{matchId}/stages` để đảm bảo dữ liệu mới nhất đã được lưu.
   - Bước 5b: Gọi `POST /api/matches/confirm-result/{matchId}` để chốt kết quả và phân định thắng thua.
```

---

## 6. Tổng Kết Bảng Mã Lỗi (Error Codes)

| HTTP Code | Error Message Phổ Biến | Nguyên Nhân & Hướng Xử Lý Cho Client |
| :--- | :--- | :--- |
| `400` | `Trận rally chưa có dữ liệu chặng nào` | Cần gọi `POST /api/matches/{matchId}/stages` lưu điểm trước khi confirm. |
| `400` | `Chưa đủ chặng để xác nhận: cần X chặng, hiện có Y` | Chưa nhập đủ tất cả các chặng theo mốc `stage_points`. |
| `400` | `Chặng X chưa hoàn thành (chưa đạt mốc)` | Điểm số của cả 2 đội ở chặng X đều chưa đạt đến `target_point`. |
| `400` | `Hai đội bằng điểm tổng kết, không thể xác nhận` | Trận Rally không được kết thúc với tỉ số hòa ở chặng cuối. |
| `422` | `Chặng X chỉ ghi được sau khi chặng Y chạm mốc` | Client cố gắng gửi điểm chặng sau khi chặng trước chưa kết thúc. |
| `422` | `Điểm lũy kế vượt mốc chặng X (Y)` | Điểm gửi lên lớn hơn target point của chặng đó. |
| `422` | `Cả hai đội cùng chạm mốc — không hợp lệ` | Điểm hai đội bằng nhau và cùng bằng đúng `target_point`. |
| `403` | `Không có quyền` | Token người dùng không thuộc BTC, trọng tài hoặc vận động viên trận đấu. |


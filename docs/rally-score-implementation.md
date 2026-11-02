# Tài Liệu Triển Khai Thể Thức Rally Score (Pickleball / Tournament)

> **Branch:** `Truong.td_implement_rally_score`  
> **Người thực hiện:** Truong2203  
> **Ngày cập nhật:** Tháng 10/2026  
> **Tài liệu tổng hợp:** Antigravity  

---

## 1. Tổng Quan & Các Commit Đã Thực Hiện

Branch `Truong.td_implement_rally_score` tập trung triển khai toàn diện hệ thống tính điểm **Rally Score theo từng chặng (stages)** cho các giải đấu có `player_per_team >= 3` (thể thức đồng đội/nhóm).

### Lịch sử các commit liên quan đến Rally Score:
1. **`a76f9bc7a`**: *Enhance tournament scoring system by introducing scoring type and stage points validation.*
   - Khởi tạo `TournamentScoringTypeInferrer`: suy luận tự động `scoring_type` (`rally` hoặc `side_out`) dựa trên `player_per_team`.
   - Validate quy tắc mốc điểm chặng (`stage_points`).
   - Cập nhật Form tạo/sửa thể thức (`FormatType.vue`, `CreateTournamentPage.vue`).
   - Viết trọn bộ Unit/Feature tests cho Request & Inferrer.
2. **`b8d144a7e`**: *Implement rally scoring system with stages management.*
   - Tạo 3 database migrations (`match_stages`, `match_stage_members`, bổ sung `match_stage_id` vào `vndupr_history`).
   - Tạo Service cốt lõi `MatchStageService` và engine xếp hạng `VnduprRatingEngine`.
   - Thêm 2 API endpoints: `POST /api/matches/{matchId}/stages` và `GET /api/matches/{matchId}/stages`.
   - Mở rộng hàm `confirmResult()` trong `MatchesController`: tự động rẽ nhánh xử lý `confirmRallyResult()`.
   - Xây dựng component giao diện `RallyScoreInput.vue`, hiển thị trên `CreateMatch.vue`, `MatchVerifyPage.vue`, `PoolStageMatchCard.vue`.
3. **`505cad756` (MỚI NHẤT về Rally Score)**: *Refactor MatchStageService and CreateMatch component to improve rally scoring logic.*
   - **Backend (`MatchStageService.php`)**: Sửa lỗi validation điểm lũy kế; cho phép đội thua có điểm 0 ở chặng mới mà không bị chặn lỗi `"Điểm lũy kế phải ≥ chặng trước"`. Xem `cumulative_score` được gửi lên như delta/điểm riêng của chặng.
   - **Frontend (`CreateMatch.vue`)**:
     - Sửa điều kiện `canConfirmMatch`: bắt buộc tất cả các chặng phải chạm mốc `target_point` mới cho phép bấm Xác nhận (tránh xác nhận trận khi chặng chưa đánh xong).
     - Trong `confirmMatchResult`: tự động lưu stages (`saveRallyStages`) xuống backend trước khi gọi API xác nhận kết quả.

---

## 2. Quy Tắc Nghiệp Vụ (Business Logic)

### 2.1. Tự Động Suy Luận Thể Thức (Scoring Type Inference)
Tất cả logic phân loại thể thức được tập trung tại `App\Services\TournamentScoringTypeInferrer`:
- `player_per_team >= 3` → **Rally Scoring** (`scoring_type = 'rally'`).
- `player_per_team < 3` (ví dụ đôi 2 người) → **Side-Out Scoring** (`scoring_type = 'side_out'`).
- Giá trị `player_per_team` hợp lệ: `2, 3, 4`. Server là nơi quyết định tối cao, client không được tự truyền đè `scoring_type`.

### 2.2. Quy Tắc Mốc Điểm Chặng (Stage Points)
- **Số chặng**: Tối thiểu 2 chặng, tối đa 5 chặng (mặc định 3 chặng).
- **Mốc điểm mặc định**: `[10, 20, 30]`.
- **Ràng buộc validation**:
  - Mỗi mốc điểm phải là số nguyên dương ≥ 1.
  - Mốc điểm phải **tăng nghiêm ngặt** (Chặng n+1 > Chặng n).
  - Khoảng cách giữa 2 mốc liền kề ≥ 5 điểm.
- **Ràng buộc an toàn dữ liệu**: Không cho phép sửa `player_per_team` của giải đấu nếu giải đấu đó đã phát sinh trận đấu hoặc stage.

---

## 3. Cấu Trúc Dữ Liệu & Database Migrations

### 3.1. Bảng `match_stages`
Lưu trữ từng chặng của trận đấu rally:
- `id`: Primary key.
- `match_id`: Khóa ngoại liên kết `matches.id` (cascade delete).
- `stage_no`: Thứ tự chặng (1..5).
- `cumulative_score_a`, `cumulative_score_b`: Điểm ghi được của đội A (Home) và đội B (Away).
- `stage_score_a`, `stage_score_b`: Điểm riêng biệt của chặng.
- `target_point`: Mốc điểm cần đạt của chặng đó (snapshot từ `stage_points`).
- `status`: `'pending'` hoặc `'completed'`.
- `winner_team_id`: Đội chạm mốc trước và giành chiến thắng chặng.
- `completed_at`: Thời gian chặng hoàn thành.
- Ràng buộc: `UNIQUE(match_id, stage_no)`.

### 3.2. Bảng `match_stage_members`
Lưu danh sách vận động viên thực tế tham gia thi đấu trong từng chặng:
- `id`: Primary key.
- `match_stage_id`: Khóa ngoại `match_stages.id`.
- `team_id`: Đội của thành viên.
- `user_id`: ID của vận động viên.
- `role`: Mặc định `'player'`.
- Ràng buộc: `UNIQUE(match_stage_id, user_id)`.

### 3.3. Bảng `vndupr_history`
- Bổ sung cột nullable: `match_stage_id`.
- Thay đổi unique index: từ `(user_id, match_id)` thành `(user_id, match_id, match_stage_id)`.
- Điều này cho phép tính toán và lưu lịch sử biến động điểm VNDUPR theo từng chặng riêng biệt của trận rally mà không bị xung đột khóa unique.

---

## 4. Chi Tiết Backend & API

### 4.1. Routes Mới (`routes/api.php`)
```php
Route::middleware(['auth:api'])->group(function () {
    Route::get('/matches/{matchId}/stages', [MatchesController::class, 'getStages']);
    Route::post('/matches/{matchId}/stages', [MatchesController::class, 'saveStages']);
});
```

### 4.2. API Lưu Stages (`saveStages`)
- **Endpoint:** `POST /api/matches/{matchId}/stages`
- **Quyền:** Phải có quyền ghi điểm trận (`hasScoringPermission`).
- **Payload:**
```json
{
  "stages": [
    {
      "stage_no": 1,
      "cumulative_score_a": 10,
      "cumulative_score_b": 6,
      "members": [
        { "user_id": 12, "team_id": 1 },
        { "user_id": 15, "team_id": 2 }
      ]
    }
  ]
}
```
- **Xử lý bên dưới (`MatchStageService::upsertStages`)**:
  - Khóa bi quan (`lockForUpdate`).
  - Kiểm tra điều kiện: Chặng n+1 chỉ được cập nhật khi chặng n đã chạm mốc `target_point`.
  - Cập nhật điểm và xác định đội thắng chặng khi chạm mốc.
  - Đồng bộ danh sách thành viên ra sân (`match_stage_members`).
  - Gọi `VnduprRatingEngine::computeAndApply()` tính xếp hạng cho chặng vừa hoàn thành.
  - Phản chiếu (mirror) dữ liệu vào `MatchResult` để tương thích bảng xếp hạng và các màn hình cũ.

### 4.3. API Xác Nhận Trận Đấu (`confirmResult`)
- Trong `MatchesController::confirmResult($matchId)`:
  - Kiểm tra nếu `MatchStage::where('match_id', $match->id)->exists()` → gọi `confirmRallyResult()`.
  - Kiểm tra xem đã đủ số chặng theo quy định chưa (`count($stages) === count($expected)`).
  - Kiểm tra từng chặng đã `isCompleted()` (đã chạm target) chưa.
  - Đội thắng chung cuộc là đội có điểm cao hơn ở chặng cuối cùng (`last stage`).
  - Cập nhật `matches.status = STATUS_COMPLETED`, cập nhật `winner_id`.
  - Dispatch event realtime: `MatchScorePublicUpdated` và `TournamentMatchUpdated`.

### 4.4. Rating Engine (`VnduprRatingEngine.php`)
- Tách riêng logic tính toán điểm ELO rating từ controller ra service chuyên trách.
- Hỗ trợ tính cho toàn trận (side-out, `match_stage_id = null`) hoặc theo từng chặng (rally, `match_stage_id != null`).
- Đảm bảo tính **Idempotent** (bỏ qua nếu user đã có lịch sử rating của chặng/trận đó).
- Hỗ trợ hàm `reverse($matchId, $matchStageId)` để hoàn tác điểm về `score_before` khi cần sửa lại kết quả chặng.

---

## 5. Chi Tiết Giao Diện Frontend (Vue.js)

### 5.1. Component `RallyScoreInput.vue`
- Cho phép xem huy hiệu thể thức chặng (ví dụ: `Rally 30 · 3 chặng · mốc 10/20/30`).
- Card tóm tắt tổng điểm chung cuộc và trạng thái đội dẫn đầu/thắng cuộc.
- Từng card chặng:
  - Chọn thành viên thi đấu cho từng đội trong chặng đó.
  - Ô nhập điểm của Đội nhà và Đội khách.
  - Tự động highlight trạng thái "THẮNG CHẶNG" khi đạt mốc điểm `target_point`.
  - Chặn nhập chặng sau khi chặng trước chưa hoàn thành.

### 5.2. Component `CreateMatch.vue` (Popup ghi điểm)
- Tích hợp `RallyScoreInput` khi phát hiện trận đấu là rally.
- **Thay đổi mới nhất trong commit `505cad756`:**
  - `canConfirmMatch`: Tính toán dựa trên việc **tất cả** các chặng đã chạm mốc:
    ```javascript
    const canConfirmMatch = computed(() => {
        if (isRally.value) {
            const stages = rallyStageData.value
            if (stages.length === 0) return false
            return stages.every(s => {
                const target = s.target_point || props.data?.stage_points?.[s.stage_no - 1] || 10
                return (s.cumulative_score_a ?? 0) >= target || (s.cumulative_score_b ?? 0) >= target
            })
        }
        return scores.value.some(s => s.team1 > 0 || s.team2 > 0)
    })
    ```
  - `confirmMatchResult`: Tự động gọi `saveRallyStages()` trước khi gọi API `confirmResults()`, đảm bảo dữ liệu stages trên server luôn mới nhất.

### 5.3. Hiển thị ở các màn hình khác
- `FormatType.vue`: Thêm giao diện chọn số chặng (2..5) và cấu hình điểm mốc khi tạo/chỉnh sửa thể thức thi đấu.
- `MatchVerifyPage.vue`: Màn hình VĐV / Trọng tài duyệt kết quả trận hiển thị chi tiết danh sách chặng và VĐV thi đấu từng chặng.
- `PoolStageMatchCard.vue` & `GroupSortView.vue`: Hiển thị điểm số chặng cuối cùng hoặc breakdown điểm rally.
- Composables:
  - `rallyScoring.js`: Các hằng số, validate mốc chặng, resize mốc chặng trên client.
  - `useRallyScoringDisplay.js`: Helper trích xuất chặng cuối, điểm đội nhà, đội khách, và xác định đội thắng chặng.

---

## 6. Tổng Kết Thay Đổi Mới Nhất (Commit `505cad756` & Hotfix UI 4 Người)

### 6.1. Thay đổi backend & logic xác nhận trong commit `505cad756`:
| STT | Vấn đề trước đó | Cách khắc phục mới nhất | File thay đổi |
|---|---|---|---|
| 1 | Validation so sánh điểm chặng sau với chặng trước `$cumA < $prevCumulativeA` gây lỗi khi một đội ghi 0 điểm ở chặng mới. | Bỏ kiểm tra so sánh giảm điểm giữa 2 chặng; coi `cumulative_score` gửi lên là điểm riêng của chặng đó (`$stageA = $cumA`). | `app/Services/MatchStageService.php` |
| 2 | Nút xác nhận trận sáng lên khi chỉ cần 1 điểm > 0, cho phép người dùng xác nhận khi trận mới đấu dở dang. | Đổi điều kiện `canConfirmMatch`: tất cả các chặng đều phải có ít nhất một đội chạm hoặc vượt `target_point`. | `resources/js/components/molecules/CreateMatch.vue` |
| 3 | Khi người dùng bấm "Xác nhận kết quả", dữ liệu chặng vừa nhập chưa kịp đồng bộ xuống database `match_stages`. | Gọi `saveRallyStages` lưu dữ liệu chặng thành công trước, sau đó mới gọi tiếp `confirmResults`. | `resources/js/components/molecules/CreateMatch.vue` |

### 6.2. Hotfix UI chọn đội 4 người bị lệch dòng (Asymmetric Flex-wrap):
- **Hiện tượng**: Khi thể thức có 4 người/đội, một bên (Đội 1) bị rớt cả 4 dòng (mỗi dòng 1 người), còn bên kia (Đội 4) lại có 2 người trên dòng đầu và 2 người rớt ở dòng 2 và dòng 3.
- **Nguyên nhân**:
  1. Thẻ cha dùng `flex flex-wrap justify-around`. Độ rộng của `UserCard` co giãn theo độ dài tên hiển thị (tối đa 80px).
  2. Các tên dài ("Dang Ha Anh Tuan", "Trần Phi Long") khiến tổng độ rộng 2 thẻ vượt quá chiều rộng container → flex ép mỗi người 1 dòng.
  3. Bên Đội 4 có tên ngắn ("Minh TQ", "Đoàn Hùng") nên vừa đủ chỗ cho 2 người trên dòng đầu, người thứ 3 và 4 tên dài lại bị ngắt dòng.
  4. Cột giữa "VS" trước đó chiếm `1fr` trong `sm:grid-cols-[2fr_1fr_2fr]`, lấy mất 20% diện tích của 2 đội.
- **Cách khắc phục**:
  1. Chuyển layout sang `grid grid-cols-[1fr_auto_1fr]` để cột VS chỉ chiếm vừa đủ chiều rộng nội dung (`auto`), nhường toàn bộ không gian cho 2 đội.
  2. Bố trí danh sách thành viên thành CSS Grid có cấu trúc rõ ràng: với 4 người dùng `grid-cols-2` (2 hàng x 2 cột cân xứng tuyệt đối trên cả 2 đội), với 2 người dùng `grid-cols-2`, với 1 người dùng căn giữa.
  3. Cung cấp avatar fallback dạng chữ viết tắt (initials) trên nền màu sinh tự động trong `UserCard.vue` khi avatar bị lỗi ảnh (tránh hiện icon ảnh vỡ và chữ alt bị cắt).

### 6.3. Tối ưu diện tích nhập điểm Rally (`RallyScoreInput.vue`):
- Đổi cụm nút tăng/giảm từ cột dọc cao 140px sang hàng ngang tiêu chuẩn `[-] [Điểm] [+]` chỉ cao 36px.
- Thu gọn ô chọn VĐV thành thanh `h-8` (32px), tích hợp điểm riêng chặng vào header, giảm >60% chiều cao mỗi thẻ chặng.
- Sửa lỗi hiển thị tổng điểm không nhảy số khi đang đấu chặng 1 hoặc chặng 2: lấy điểm từ `latestActiveStage` thay vì chặng cuối cùng của mảng.

### 6.4. Quyết định nghiệp vụ màn hình Trọng tài:
- **Quyết định:** Thể thức Rally Score **chỉ nhập điểm trực tiếp theo từng chặng trên popup trận đấu**, tạm thời không dùng màn hình trọng tài (vốn dành cho Side-out theo Set).
- **Thực hiện:** Đã bỏ nút "Nhập điểm trọng tài" ở các trận đấu Rally trong `CreateMatch.vue`.

---

## 7. Các File Kiểm Thử (Tests) Đã Viết
- `tests/Feature/MatchStageServiceTest.php`: Kiểm thử lưu stage, validate mốc chặng, chuyển trạng thái trận đấu.
- `tests/Feature/VnduprRatingEngineTest.php`: Kiểm thử tính điểm VNDUPR theo chặng, tính toán ELO, reverse rating.
- `tests/Feature/TournamentTypeStagePointsTest.php`: Kiểm thử validation mốc chặng tại tầng Controller / Request.
- `tests/Feature/UpdateTournamentPlayerPerTeamGuardTest.php`: Kiểm thử khóa đổi số người mỗi đội khi đã có trận.
- `tests/Unit/TournamentScoringTypeInferrerTest.php`: Unit test thuật toán suy luận `inferScoringType` và validate mốc điểm.


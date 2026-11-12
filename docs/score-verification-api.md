# Tài liệu API Xác minh Điểm trình (Score Verification)

## Đường dẫn gốc (Base URL)

```
/api
```

---

## Các API dành cho Người dùng (Đã đăng nhập)

### 1. Tạo yêu cầu xác minh điểm trình

**POST** `/score-verifications`

**Headers:**

```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Dữ liệu gửi lên (Form Data):**

| Trường (Field) | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `score_type` | string | Có | Loại điểm xác minh: `SPCN` hoặc `DUPR` |
| `score` | number | Có | Giá trị điểm (từ 0 đến 8) |
| `image` | file | Có | File ảnh bằng chứng (jpeg, png, jpg, gif; tối đa 5MB) |

**Phản hồi thành công (201 Created):**

```json
{
    "success": true,
    "message": "Yêu cầu xác minh đã được gửi",
    "data": {
        "id": 1,
        "request_number": "SV-20260717-000001",
        "score_type": "SPCN",
        "submitted_score": "2.000",
        "picki_score": null,
        "current_picki_score": null,
        "difference": null,
        "threshold": 0.5,
        "is_over_threshold": false,
        "status": "PENDING",
        "created_at": "2026-07-17T12:00:00Z",
        "is_new": true
    }
}
```

**Phản hồi lỗi (422 - Đang có yêu cầu chờ duyệt):**

```json
{
    "success": false,
    "message": "Bạn đang có yêu cầu đang chờ duyệt, vui lòng chờ xử lý trước khi gửi yêu cầu mới.",
    "errors": {
        "score_type": [
            "Bạn đang có yêu cầu đang chờ duyệt, vui lòng chờ xử lý trước khi gửi yêu cầu mới."
        ]
    }
}
```

**Phản hồi lỗi (422 - Dữ liệu không hợp lệ):**

```json
{
    "success": false,
    "message": "Dữ liệu cung cấp không hợp lệ.",
    "errors": {
        "score_type": ["Vui lòng chọn loại điểm."],
        "score": ["Vui lòng nhập điểm."],
        "image": ["Vui lòng tải lên ảnh chứng minh."]
    }
}
```

---

### 2. Xem lịch sử yêu cầu xác minh của bản thân

**GET** `/score-verifications`

**Headers:**

```
Authorization: Bearer {token}
```

**Tham số truy vấn (Query Parameters):**

| Tham số | Kiểu dữ liệu | Mặc định | Mô tả |
| :--- | :--- | :--- | :--- |
| `status` | string | - | Lọc theo trạng thái: `PENDING`, `APPROVED`, hoặc `REJECTED` |
| `page` | integer | 1 | Số trang |
| `per_page` | integer | 20 | Số lượng bản ghi mỗi trang (tối đa 100) |

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "data": {
        "items": [...],
        "meta": {
            "total": 5,
            "page": 1,
            "per_page": 20
        }
    }
}
```

---

## Các API dành cho Quản trị viên (Admin - `super_admin`)

**Headers chung:**

```
Authorization: Bearer {token}
```

---

### 3. Bảng điều khiển & Danh sách yêu cầu xác minh

**GET** `/admin/score-verifications`

**Tham số truy vấn (Query Parameters):**

| Tham số | Kiểu dữ liệu | Mặc định | Mô tả |
| :--- | :--- | :--- | :--- |
| `status` | string | `PENDING` | Lọc theo trạng thái: `PENDING`, `APPROVED`, hoặc `REJECTED` |
| `score_type` | string | - | Lọc theo loại điểm: `SPCN` hoặc `DUPR` |
| `keyword` | string | - | Tìm kiếm theo họ và tên người dùng |
| `from_date` | date | - | Lọc từ ngày (định dạng: YYYY-MM-DD) |
| `to_date` | date | - | Lọc đến ngày (định dạng: YYYY-MM-DD) |
| `page` | integer | 1 | Số trang |
| `per_page` | integer | 20 | Số lượng bản ghi mỗi trang (tối đa 100) |

**Ví dụ:**

```
GET /admin/score-verifications?status=PENDING&score_type=SPCN&page=1&per_page=20
```

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "data": {
        "summary": {
            "pending": 5,
            "approved": 12,
            "rejected": 3
        },
        "items": [
            {
                "id": 1,
                "request_number": "SV-20260717-000001",
                "user": {
                    "id": 1,
                    "full_name": "Nguyễn Văn A",
                    "avatar_url": "https://..."
                },
                "score_type": "SPCN",
                "submitted_score": "2.000",
                "picki_score": null,
                "current_picki_score": "1.448",
                "difference": 0.552,
                "threshold": 0.5,
                "is_over_threshold": true,
                "status": "PENDING",
                "created_at": "2026-07-17T12:00:00Z",
                "is_new": true
            }
        ],
        "meta": {
            "total": 5,
            "page": 1,
            "per_page": 20
        }
    }
}
```

---

### 4. Chi tiết yêu cầu xác minh

**GET** `/admin/score-verifications/{verification}`

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "request_number": "SV-20260717-000001",
        "user": {
            "id": 1,
            "full_name": "Nguyễn Văn A",
            "avatar_url": "https://..."
        },
        "image_url": "https://storage.example.com/verification/xxx.jpg",
        "score_type": "SPCN",
        "submitted_score": "2.000",
        "picki_score": 2.500,
        "current_picki_score": "1.448",
        "difference": 0.552,
        "threshold": 0.5,
        "is_over_threshold": true,
        "status": "PENDING",
        "created_at": "2026-07-17T12:00:00Z",
        "reviewed_at": null,
        "reviewer": null,
        "rejection_reason": null,
        "award_anchor_badge": false,
        "is_new": true
    }
}
```

---

### 5. Phê duyệt yêu cầu (Duyệt lần đầu)

**POST** `/admin/score-verifications/{verification}/approve`

Dùng khi admin duyệt yêu cầu đang chờ xử lý (`PENDING`). Hệ thống sẽ cập nhật điểm SPCN/DUPR, điểm trình Picki (`vndupr_score`) nếu có điều chỉnh, chuyển trạng thái sang `APPROVED`, trao huy hiệu Anchor (nếu chọn) và gửi thông báo cho user.

**Dữ liệu gửi lên (Request Body):**

```json
{
    "picki_score": 2.500,
    "award_anchor_badge": true
}
```

| Trường (Field) | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `picki_score` | number / string | Không | Điểm trình Picki (`vndupr_score`) được admin điều chỉnh lại cho user (tối thiểu: 1.0, tối đa: 10.0). Nếu không truyền hoặc truyền `null` thì điểm trình Picki hiện tại của user sẽ được giữ nguyên. |
| `award_anchor_badge` | boolean | Không | Trao huy hiệu Anchor cho user (`true` / `false`, mặc định: `false`). |

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "message": "Đã duyệt yêu cầu",
    "data": {
        "status": "APPROVED"
    }
}
```

**Phản hồi lỗi (400 Bad Request):**

```json
{
    "success": false,
    "message": "Request cannot be processed in its current status",
    "code": 400
}
```

---

### 6. Cập nhật lại điểm trình Picki (Khi yêu cầu ĐÃ DUYỆT)

**POST** `/admin/score-verifications/{verification}/update-picki-score`

Dùng khi yêu cầu đã được duyệt (`APPROVED`), admin vào lại màn hình này để sửa lại điểm trình Picki của user.

**Dữ liệu gửi lên (Request Body):**

```json
{
    "picki_score": 2.800,
    "award_anchor_badge": true
}
```

| Trường (Field) | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `picki_score` | number / string | **Bắt buộc** | Điểm trình Picki (`vndupr_score`) mới cập nhật cho user (tối thiểu: 1.0, tối đa: 10.0). Hệ thống sẽ cập nhật trực tiếp vào bảng `user_sport_scores` của user. |
| `award_anchor_badge` | boolean | Không | Cập nhật trạng thái trao huy hiệu Anchor (nếu truyền `true` và user chưa có thì hệ thống sẽ trao badge). |

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "message": "Cập nhật điểm trình Picki thành công",
    "data": {
        "status": "APPROVED",
        "picki_score": 2.8,
        "item": {
            "id": 1,
            "request_number": "SV-20260717-000001",
            "score_type": "SPCN",
            "submitted_score": "2.000",
            "picki_score": 2.8,
            "current_picki_score": 2.8,
            "difference": 0.8,
            "threshold": 0.5,
            "is_over_threshold": true,
            "status": "APPROVED",
            "award_anchor_badge": true,
            "reviewed_at": "2026-11-12T03:30:00.000000Z",
            "reviewer": {
                "id": 10,
                "full_name": "Admin Quản Trị"
            },
            "user": {
                "id": 1,
                "full_name": "Nguyễn Văn A",
                "avatar_url": "https://..."
            }
        }
    }
}
```

**Phản hồi lỗi (400 - Yêu cầu chưa được duyệt):**

```json
{
    "success": false,
    "message": "Chỉ có thể sửa điểm trình Picki cho yêu cầu đã được phê duyệt",
    "code": 400
}
```

---

### 7. Từ chối yêu cầu

**POST** `/admin/score-verifications/{verification}/reject`

**Dữ liệu gửi lên (Request Body):**

```json
{
    "reason": "Điểm không khớp với hồ sơ"
}
```

| Trường (Field) | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `reason` | string | Có | Lý do từ chối (tối đa 500 ký tự) |

**Phản hồi thành công (200 OK):**

```json
{
    "success": true,
    "message": "Đã từ chối yêu cầu",
    "data": {
        "status": "REJECTED"
    }
}
```

---

## Bảng tổng hợp các Route (Route Summary)

```
# Các Route Người dùng (Cần đăng nhập)
POST   /score-verifications                              - Tạo yêu cầu xác minh điểm
GET    /score-verifications                              - Lấy lịch sử xác minh điểm

# Thông tin xác minh điểm qua endpoint /me
# Các trường spcn_request và dupr_request được đính kèm trực tiếp trong response GET /me

# Các Route Admin (Quyền super_admin)
GET    /admin/score-verifications                       - Bảng tổng quan + Danh sách yêu cầu
GET    /admin/score-verifications/{id}                   - Chi tiết yêu cầu
POST   /admin/score-verifications/{id}/approve           - Duyệt yêu cầu (Trạng thái PENDING)
POST   /admin/score-verifications/{id}/update-picki-score - Sửa lại điểm trình Picki (Trạng thái APPROVED)
POST   /admin/score-verifications/{id}/reject            - Từ chối yêu cầu
```

---

## Mô tả chi tiết các trường phản hồi (Response Fields)

| Trường (Field) | Mô tả |
| :--- | :--- |
| `request_number` | Mã yêu cầu định danh duy nhất (định dạng: `SV-YYYYMMDD-NNNNNN`) |
| `image_url` | Đường dẫn ảnh bằng chứng xác minh điểm đã tải lên |
| `score_type` | Loại điểm xác minh (`SPCN` hoặc `DUPR`) |
| `submitted_score` | Điểm số người dùng tự khai báo và gửi lên |
| `picki_score` | Điểm trình Picki (`vndupr_score`) được admin phê duyệt hoặc điều chỉnh lại |
| `current_picki_score` | Điểm trình Picki hiện tại của người dùng trong hệ thống (`user_sport_scores`) |
| `difference` | Độ chênh lệch tuyệt đối giữa điểm người dùng khai báo và điểm Picki hiện tại |
| `threshold` | Ngưỡng chênh lệch cảnh báo tối đa (mặc định: 0.5) |
| `is_over_threshold` | `true` nếu độ chênh lệch vượt quá ngưỡng cảnh báo (`difference > threshold`) |
| `is_new` | `true` nếu yêu cầu được tạo trong vòng 24 giờ qua |

---

## Thông tin xác minh điểm trong API `/me`

Endpoint `GET /me` trả về trực tiếp thông tin xác minh điểm qua 2 trường `spcn_request` và `dupr_request`.

**Ví dụ phản hồi (trích đoạn):**

```json
{
    "id": 1,
    "full_name": "Nguyễn Văn A",
    "email": "user@example.com",
    "avatar_url": "https://...",
    "spcn_request": {
        "id": 1,
        "request_number": "SV-20260717-000001",
        "image_url": "https://storage.example.com/verification/xxx.jpg",
        "submitted_score": "2.000",
        "picki_score": 2.500,
        "current_picki_score": "1.448",
        "difference": 0.552,
        "threshold": 0.5,
        "is_over_threshold": true,
        "status": "PENDING",
        "created_at": "2026-07-17T12:00:00Z",
        "is_new": true
    },
    "dupr_request": null
}
```

**Ghi chú:**
- Nếu người dùng chưa từng gửi yêu cầu cho loại điểm đó, trường tương ứng sẽ trả về `null`.
- Cấu trúc các trường trong `spcn_request` và `dupr_request` hoàn toàn giống với API chi tiết xác minh điểm.

---

## Phân loại điểm (Score Types)

| Loại (Type) | Tên hiển thị | Ý nghĩa |
| :--- | :--- | :--- |
| `SPCN` | Điểm SPCN | Điểm trình từ hệ thống SPCN |
| `DUPR` | Điểm DUPR | Điểm trình quốc tế DUPR |

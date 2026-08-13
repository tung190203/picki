## 1. Tìm kiếm người dùng

Tìm kiếm những người dùng đang hoạt động có thể được gộp.

**Endpoint:** `GET /api/admin/users/search`

### Tham số Query

| Tham số | Kiểu | Bắt buộc | Mô tả |
|---------|------|----------|-------|
| `q` | string | Không | Từ khóa tìm kiếm (tên, số điện thoại, email hoặc ID) |
| `page` | integer | Không | Số trang (mặc định: 1) |
| `limit` | integer | Không | Số item mỗi trang (mặc định: 20, tối đa: 50) |

### Response

```json
{
    "status": true,
    "message": "Tìm kiếm user thành công",
    "data": [
        {
            "id": 1,
            "full_name": "Nguyễn Văn A",
            "email": "nguyenvana@example.com",
            "phone": "0987654321",
            "avatar_url": "https://...",
            "is_banned": false,
            "is_merged": false,
            "created_at": "2026-01-15T10:30:00+07:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100,
        "last_page": 5
    }
}
```

### Ghi chú
- Loại trừ người dùng có `is_merged = true`
- Loại trừ người dùng có `is_banned = true`
- Loại trừ người dùng khách (`is_guest = true`)

---

## 2. Xem trước gộp

Xem trước việc gộp giữa hai người dùng, phát hiện các trận đấu trùng lặp.

**Endpoint:** `POST /api/admin/user-merges/preview`

### Request Body

```json
{
    "user_a_id": 1,
    "user_b_id": 2
}
```

| Trường | Kiểu | Bắt buộc | Mô tả |
|--------|------|----------|-------|
| `user_a_id` | integer | Có | ID của người dùng thứ nhất |
| `user_b_id` | integer | Có | ID của người dùng thứ hai |

### Response

```json
{
    "status": true,
    "message": "Preview merge thành công",
    "data": {
        "user_a": {
            "id": 1,
            "full_name": "Nguyễn Văn A",
            "phone": "0987654321",
            "email": "nguyenvana@example.com",
            "avatar_url": "https://...",
            "rating": 0.58,
            "played_matches": 12,
            "match_breakdown": {
                "tournament": 8,
                "quick_match": 3,
                "mini_tournament": 1,
                "total": 12
            }
        },
        "user_b": {
            "id": 2,
            "full_name": "Trần Văn B",
            "phone": "0987654322",
            "email": "tranvanb@example.com",
            "avatar_url": "https://...",
            "rating": 0.42,
            "played_matches": 5,
            "match_breakdown": {
                "tournament": 3,
                "quick_match": 2,
                "mini_tournament": 0,
                "total": 5
            }
        },
        "duplicate_matches": [
            {
                "match_id": 123,
                "match_type": "tournament",
                "match_name": "Minigame GreenField",
                "tournament_id": 10,
                "tournament_name": "GreenField Cup",
                "played_at": "2026-08-02",
                "reason": "Cùng trận đấu giải đấu: GreenField Cup"
            }
        ],
        "duplicate_count": 1,
        "match_summary": {
            "user_a_matches": 12,
            "user_b_matches": 5,
            "duplicate_matches": 1,
            "merged_matches": 16
        },
        "can_continue": false
    }
}
```

### Các loại trận trùng lặp

| Loại | Logic phát hiện |
|------|----------------|
| `tournament` | Người dùng cùng đội hoặc đội đối lập trong cùng một trận đấu giải đấu |
| `quick_match` | Cả hai người dùng đều có bản ghi trong cùng một trận quick match |
| `mini_tournament` | Người dùng cùng đội hoặc đội đối lập trong cùng một trận mini tournament |

### Response lỗi

| Status | Code | Mô tả |
|--------|------|-------|
| 404 | - | Không tìm thấy user |
| 400 | - | Hai user phải khác nhau |

---

## 3. Xem trước cuối cùng

Xem trước kết quả gộp cuối cùng sau khi chọn người dùng sống sót (survivor).

**Endpoint:** `POST /api/admin/user-merges/preview-final`

### Request Body

```json
{
    "user_a_id": 1,
    "user_b_id": 2,
    "survivor_user_id": 1,
    "duplicate_override": true
}
```

| Trường | Kiểu | Bắt buộc | Mô tả |
|--------|------|----------|-------|
| `user_a_id` | integer | Có | ID của người dùng thứ nhất |
| `user_b_id` | integer | Có | ID của người dùng thứ hai |
| `survivor_user_id` | integer | Có | ID của người dùng được giữ lại (phải là A hoặc B) |
| `duplicate_override` | boolean | Có | Bỏ qua cảnh báo trận trùng lặp |

### Response

```json
{
    "status": true,
    "message": "Preview cuối cùng thành công",
    "data": {
        "survivor": {
            "id": 1,
            "full_name": "Nguyễn Văn A",
            "phone": "0987654321",
            "email": "nguyenvana@example.com",
            "avatar_url": "https://..."
        },
        "merged_user": {
            "id": 2,
            "full_name": "Trần Văn B",
            "phone": "0987654322",
            "email": "tranvanb@example.com",
            "avatar_url": "https://..."
        },
        "selected_info": {
            "name": "Nguyễn Văn A",
            "phone": "0987654321",
            "email": "nguyenvana@example.com",
            "avatar_url": "https://..."
        },
        "match_summary": {
            "survivor_matches": 12,
            "merged_user_matches": 5,
            "duplicate_matches": 1,
            "final_matches": 16
        },
        "estimated_rating": 0.54,
        "login_warning": true
    }
}
```

### Tính toán rating ước tính

Rating ước tính được tính theo số lượng bản ghi lịch sử thi đấu của mỗi người dùng:
- Công thức: `(survivor_rating * survivor_weight) + (merged_rating * merged_weight)`
- Trọng số tỷ lệ với số bản ghi vndupr_history

### Response lỗi

| Status | Code | Mô tả |
|--------|------|-------|
| 400 | `DUPLICATE_OVERRIDE_REQUIRED` | Phát hiện trùng lặp nhưng `duplicate_override` là false |

---

## 4. Thực hiện gộp

Thực hiện thao tác gộp người dùng.

**Endpoint:** `POST /api/admin/user-merges`

### Request Body

```json
{
    "user_a_id": 1,
    "user_b_id": 2,
    "survivor_user_id": 1,
    "duplicate_override": true,
    "confirmation_name": "Nguyễn Văn A"
}
```

| Trường | Kiểu | Bắt buộc | Mô tả |
|--------|------|----------|-------|
| `user_a_id` | integer | Có | ID của người dùng thứ nhất |
| `user_b_id` | integer | Có | ID của người dùng thứ hai |
| `survivor_user_id` | integer | Có | ID của người dùng được giữ lại (phải là A hoặc B) |
| `duplicate_override` | boolean | Có | Bỏ qua cảnh báo trận trùng lặp |
| `confirmation_name` | string | Có | Tên chính xác của người dùng được giữ lại (phân biệt hoa thường) |

### Response

```json
{
    "status": true,
    "message": "Merge user thành công",
    "data": {
        "id": 1001,
        "survivor_user_id": 1,
        "merged_user_id": 2,
        "status": "completed",
        "matches_after_merge": 16,
        "final_rating": 0.54
    }
}
```

### Các bước xác thực

Backend sẽ xác thực lại:
1. Cả hai user đều tồn tại và chưa bị gộp
2. `survivor_user_id` phải là A hoặc B
3. `confirmation_name` phải khớp chính xác với `full_name` của survivor
4. Nếu có trùng lặp, `duplicate_override` phải là `true`
5. Không có thao tác gộp đồng thời trên cùng người dùng

### Các thay đổi sau khi gộp

| Thay đổi | Mô tả |
|----------|-------|
| User bị gộp | `is_merged = true`, `merged_into_user_id = survivor.id` |
| User survivor | Không thay đổi, tiếp tục hoạt động bình thường |
| Thành viên đội | Chuyển từ user gộp sang survivor (nếu không trùng lặp) |
| Lịch sử thi đấu | Chuyển từ user gộp sang survivor (nếu không trùng lặp) |
| Tham gia giải đấu | Chuyển từ user gộp sang survivor (nếu không trùng lặp) |
| Thể thao/huy hiệu | Xóa các bản ghi trùng lặp |
| Đăng nhập | User bị gộp không thể đăng nhập |

### Response lỗi

| Status | Code | Mô tả |
|--------|------|-------|
| 400 | - | Tên xác nhận không khớp |
| 400 | `DUPLICATE_OVERRIDE_REQUIRED` | Có trùng lặp nhưng không bỏ qua |
| 400 | - | User đã được gộp trước đó |
| 400 | - | Survivor phải là một trong hai user được chọn |
| 404 | - | Không tìm thấy user |

---

## 5. Danh sách lịch sử gộp

Lấy danh sách phân trang các thao tác gộp.

**Endpoint:** `GET /api/admin/user-merges`

### Tham số Query

| Tham số | Kiểu | Bắt buộc | Mô tả |
|---------|------|----------|-------|
| `page` | integer | Không | Số trang (mặc định: 1) |
| `limit` | integer | Không | Số item mỗi trang (mặc định: 15, tối đa: 100) |
| `search` | string | Không | Tìm kiếm theo tên user |
| `performed_by` | integer | Không | Lọc theo admin thực hiện |
| `status` | string | Không | Lọc theo trạng thái: `pending`, `completed`, `failed` |
| `date_from` | date | Không | Lọc từ ngày (YYYY-MM-DD) |
| `date_to` | date | Không | Lọc đến ngày (YYYY-MM-DD) |

### Response

```json
{
    "status": true,
    "message": "Lấy lịch sử merge thành công",
    "data": [
        {
            "id": 1001,
            "survivor": {
                "id": 1,
                "name": "Nguyễn Văn A"
            },
            "merged_user": {
                "id": 2,
                "name": "Trần Văn B"
            },
            "matches_after_merge": 16,
            "duplicate_count": 1,
            "duplicate_override": true,
            "performed_by": {
                "id": 10,
                "name": "Admin"
            },
            "created_at": "2026-08-12T11:32:45+07:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 50,
        "last_page": 4
    }
}
```

---

## 6. Chi tiết gộp

Lấy thông tin chi tiết về một thao tác gộp cụ thể.

**Endpoint:** `GET /api/admin/user-merges/{id}`

### Tham số Path

| Tham số | Kiểu | Bắt buộc | Mô tả |
|---------|------|----------|-------|
| `id` | integer | Có | ID của bản ghi gộp |

### Response

```json
{
    "status": true,
    "message": "Lấy chi tiết merge thành công",
    "data": {
        "id": 1001,
        "survivor": {
            "id": 1,
            "name": "Nguyễn Văn A",
            "phone": "0987654321",
            "email": "nguyenvana@example.com"
        },
        "merged_user": {
            "id": 2,
            "name": "Trần Văn B",
            "phone": "0987654322",
            "email": "tranvanb@example.com"
        },
        "match_summary": {
            "survivor_matches": 12,
            "merged_user_matches": 5,
            "duplicate_matches": 1,
            "matches_after_merge": 16
        },
        "duplicate_matches": [
            {
                "match_id": 123,
                "match_type": "tournament",
                "match_name": "Minigame GreenField",
                "reason": "Cùng trận đấu giải đấu: GreenField Cup"
            }
        ],
        "duplicate_override": true,
        "selected_info": {
            "name": "Nguyễn Văn A",
            "phone": "0987654321",
            "email": "nguyenvana@example.com",
            "avatar_url": "https://..."
        },
        "estimated_rating": 0.54,
        "final_rating": 0.54,
        "status": "completed",
        "confirmation_name": "Nguyễn Văn A",
        "performed_by": {
            "id": 10,
            "name": "Admin"
        },
        "created_at": "2026-08-12T11:32:45+07:00",
        "completed_at": "2026-08-12T11:32:50+07:00",
        "metadata": {
            "survivor_snapshot": {
                "full_name": "Nguyễn Văn A",
                "phone": "0987654321",
                "email": "nguyenvana@example.com"
            },
            "merged_snapshot": {
                "full_name": "Trần Văn B",
                "phone": "0987654322",
                "email": "tranvanb@example.com"
            },
            "duplicate_matches": [...]
        }
    }
}
```

---

## Định dạng Response lỗi

Tất cả các lỗi đều theo định dạng này:

```json
{
    "status": false,
    "message": "Thông báo lỗi bằng tiếng Việt",
    "errors": {},
    "data": {
        "status_code": "ERROR_CODE"
    }
}
```

### Mã trạng thái

| Code | Mô tả |
|------|-------|
| `USER_NOT_FOUND` | Người dùng không tồn tại |
| `USER_BANNED` | Tài khoản người dùng bị khóa |
| `USER_MERGED` | Tài khoản người dùng đã được gộp (không thể đăng nhập) |
| `DUPLICATE_OVERRIDE_REQUIRED` | Phát hiện trận trùng lặp, cần bỏ qua |
| `CONFIRMATION_NAME_MISMATCH` | Tên xác nhận không khớp với survivor |
| `USERS_MUST_BE_DIFFERENT` | Không thể gộp user với chính nó |
| `INVALID_SURVIVOR` | Survivor phải là một trong hai user được chọn |
| `USER_ALREADY_MERGED` | User đã được gộp trước đó |
| `MERGE_ALREADY_IN_PROGRESS` | Thao tác gộp đang được thực hiện |

---

## Tóm tắt quy tắc nghiệp vụ

1. **Không xóa cứng**: Người dùng bị gộp không bao giờ bị xóa, chỉ được đánh dấu `is_merged = true`
2. **Bảo toàn dữ liệu**: Dữ liệu lịch sử thi đấu được giữ lại và chuyển giao
3. **Ngăn chặn trùng lặp**: Các trận trùng lặp được phát hiện nhưng không tự động xóa
4. **Yêu cầu xác nhận**: Admin phải nhập chính xác tên survivor để xác nhận
5. **Cảnh báo override**: Các trùng lặp yêu cầu xác nhận bỏ qua rõ ràng
6. **Nhật ký kiểm tra**: Tất cả thao tác được ghi lại trong bảng `user_merges`
7. **Bảo vệ đồng thời**: Database row locking ngăn chặn race conditions

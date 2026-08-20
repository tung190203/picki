# Quản lý Banner Carousel 

## 1. Lấy danh sách Banner (Super Admin)
Lấy danh sách banner phân nhóm "Đang hiển thị", "Đã kết thúc" và danh sách 4 nhóm đối tượng người dùng.

- **Method:** `GET`
- **URL:** `/admin/banners`

### Response (200 OK):
```json
{
  "status": true,
  "message": "Success",
  "data": {
    "active_banners": [
      {
        "id": 134,
        "internal_name": "Picki tặng bạn",
        "title": "Picki tặng bạn",
        "image_url": "banners/1787216642.jpg",
        "link_type": "external_url",
        "link_value": "https://facebook.com",
        "start_date": "2026-08-20",
        "end_date": "2026-09-19",
        "audience_segment_ids": ["ALL"],
        "display_order": 1,
        "is_enabled": true,
        "status_badge": "live",
        "days_remaining": 30
      }
    ],
    "ended_banners": [],
    "audience_segments": [
      { "id": "ALL", "name": "Tất cả user", "estimated_count": 824 },
      { "id": "NEW_USERS", "name": "User mới (≤30 ngày)", "estimated_count": 168 },
      { "id": "TOURNAMENT_USERS", "name": "User đã tham gia giải đấu", "estimated_count": 280 },
      { "id": "INACTIVE_USERS", "name": "User không hoạt động (14+ ngày)", "estimated_count": 822 }
    ],
    "total_active": 1,
    "total_ended": 0
  }
}
```

---

## 2. Tạo Banner mới (Super Admin)
- **Method:** `POST`
- **URL:** `/admin/banners`
- **Content-Type:** `multipart/form-data` hoặc `application/json`

### Body Parameters:
| Parameter | Type | Required | Description |
| :--- | :--- | :---: | :--- |
| `internal_name` | string | Không | Tên nội bộ phân biệt trong Admin (Mặc định: `Banner #ID`) |
| `image` | file | Không | Tệp ảnh upload (PNG/JPG/WEBP, Max 5MB) |
| `image_url` | string | Không | Link ảnh sẵn có |
| `link_type` | string | Không | `none` \| `internal_deeplink` \| `external_url` |
| `link_value` | string | Không | Đường dẫn Deep link (vd: `/rewards/tang-ban`) hoặc URL web |
| `start_date` | date | Không | Ngày bắt đầu (`YYYY-MM-DD`, mặc định: Hôm nay) |
| `end_date` | date | Không | Ngày kết thúc (`YYYY-MM-DD`, mặc định: Hôm nay + 30 ngày) |
| `audience_segment_ids[]` | array | Không | Mảng phân khúc: `ALL`, `NEW_USERS`, `TOURNAMENT_USERS`, `INACTIVE_USERS` |
| `display_order` | int | Không | Thứ tự hiển thị (Mặc định: tự tăng) |
| `is_enabled` | boolean / int | Không | `1` (Bật) / `0` (Tắt) |

### Response (201 Created):
```json
{
  "status": true,
  "message": "Tạo banner mới thành công",
  "data": {
    "id": 135,
    "internal_name": "Banner quảng cáo #1",
    "image_url": "banners/1787216642.jpg",
    "link_type": "external_url",
    "link_value": "https://facebook.com",
    "start_date": "2026-08-20",
    "end_date": "2026-09-20",
    "audience_segment_ids": ["ALL"],
    "display_order": 1,
    "is_enabled": true,
    "status_badge": "live",
    "days_remaining": 31
  }
}
```

---

## 3. Cập nhật Banner
- **Method:** `POST` hoặc `PUT`
- **URL:** `/admin/banners/{id}`
- **Body:** Tương tự API Tạo banner (truyền các trường cần cập nhật).

---

## 4. Đổi thứ tự Carousel (Reorder)
- **Method:** `POST`
- **URL:** `/admin/banners/reorder`

### Body JSON:
```json
{
  "orders": [
    { "id": 134, "display_order": 1 },
    { "id": 135, "display_order": 2 }
  ]
}
```

### Response (200 OK):
```json
{
  "status": true,
  "message": "Cập nhật thứ tự banner thành công",
  "data": null
}
```

---

## 5. Xóa Banner
- **Method:** `DELETE`
- **URL:** `/admin/banners/{id}`

### Response (200 OK):
```json
{
  "status": true,
  "message": "Đã xóa banner",
  "data": null
}
```

---

## 6. Lấy Banner Trang chủ (Client App / Mobile User)
Lấy danh sách Banner hiển thị trên Carousel trang chủ (Đã tự động lọc theo lịch `[start_date, end_date]`, `is_enabled=true` và đúng đối tượng `audience_segment_ids` của User).

- **Method:** `GET`
- **URL:** `/home`
- **Header:** `Authorization: Bearer <TOKEN>` (Tùy chọn)

### Response Sample:
```json
{
  "status": true,
  "message": "Success",
  "data": {
    "banners": [
      {
        "id": 134,
        "title": "Picki tặng bạn",
        "image_url": "http://localhost:8000/storage/banners/1787216642.jpg",
        "link": "https://facebook.com",
        "type": "image",
        "is_active": true,
        "order": 1
      }
    ]
  }
}
```

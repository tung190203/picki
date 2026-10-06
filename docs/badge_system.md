# Tài liệu API & Hệ thống — Tính năng Huy hiệu (Badge System)

Tài liệu này tổng hợp toàn bộ các API, cấu trúc cơ sở dữ liệu và quy tắc nghiệp vụ trong Hệ thống Huy hiệu (Badge System) mới được triển khai, thay thế cho hệ thống lưu trữ bằng text code cũ.

---

## 1. Cấu trúc Cơ sở dữ liệu (Database)

Hệ thống sử dụng 3 bảng chính:
- **`badge_types`**: Danh mục phân loại huy hiệu (`id`, `code`, `name`).
- **`badges`**: Chứa thông tin huy hiệu (`id`, `code`, `name`, `description`, `icon_url`, `type`, `priority`, `is_active`).
- **`user_badges`**: Bảng trung gian lưu lịch sử user nhận huy hiệu (`id`, `user_id`, `badge_id`, `is_featured`, `acquired_at`). *Đã chuyển từ cột `badge_code` sang dùng `badge_id`.*

---

## 2. Danh sách Endpoints API

| STT | Phương thức | Endpoint | Dành cho | Mô tả |
| --- | --- | --- | --- | --- |
| 1 | `GET` | `/api/admin/badge-types` | Admin | Lấy danh sách phân loại huy hiệu |
| 2 | `POST` | `/api/admin/badge-types` | Admin | Tạo mới phân loại huy hiệu |
| 3 | `PUT` | `/api/admin/badge-types/{id}` | Admin | Cập nhật phân loại huy hiệu |
| 4 | `GET` | `/api/admin/badges` | Admin | Lấy danh sách toàn bộ huy hiệu |
| 5 | `POST` | `/api/admin/badges` | Admin | Tạo mới huy hiệu (có hỗ trợ upload icon) |
| 6 | `PUT` | `/api/admin/badges/{badge}` | Admin | Cập nhật huy hiệu (có hỗ trợ thay đổi icon) |
| 7 | `GET` | `/api/user/{id}/badges` | User | Lấy danh sách huy hiệu của một User cụ thể |
| 8 | `PUT` | `/api/user/badges/featured` | User | Cập nhật (Ghim) các huy hiệu nổi bật của User đang đăng nhập |

---

## 3. Chi tiết các Endpoints

### 3.1. Lấy danh sách phân loại huy hiệu (Badge Types)
Lấy danh sách các loại huy hiệu có trong hệ thống (VD: Hạng, Sự kiện).

* **Endpoint:** `GET /api/admin/badge-types`
* **Headers:** `Authorization: Bearer {token}`

#### Response mẫu (`200 OK`):
```json
{
  "data": [
    {
      "id": 1,
      "code": "scarce",
      "name": "Hiếm",
      "created_at": "2026-10-05T09:00:00.000000Z"
    }
  ]
}
```

---

### 3.2. Tạo mới / Sửa Huy hiệu (Tích hợp Upload Icon)
Thêm mới một huy hiệu vào hệ thống.

* **Endpoint:** `POST /api/admin/badges` (Tạo) | `PUT /api/admin/badges/{badge}` (Sửa)
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`multipart/form-data`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `code` | string | **Có** | Mã code huy hiệu (Viết hoa, không dấu, vd: `VERIFIED`) |
| `name` | string | **Có** | Tên hiển thị (VD: Đã xác minh) |
| `type` | string | **Có** | Mã của Loại huy hiệu (VD: `scarce`) |
| `icon` | file | Không | File ảnh SVG/PNG tải lên |
| `is_active`| boolean | Không | Trạng thái hiển thị (1 hoặc 0) |

#### Response mẫu (`201 Created`):
```json
{
  "data": {
    "id": 1,
    "code": "VERIFIED",
    "name": "Đã xác minh",
    "icon_url": "/storage/badges/image.svg",
    "type": "scarce"
  }
}
```

---

### 3.3. Lấy danh sách huy hiệu của một User
Lấy toàn bộ huy hiệu trong hệ thống, kèm theo trạng thái người dùng này đã mở khoá hay chưa (`is_unlocked`).

* **Endpoint:** `GET /api/user/{id}/badges`
* **Headers:** `Authorization: Bearer {token}`

#### Response mẫu (`200 OK`):
```json
{
  "data": [
    {
      "id": 1,
      "user_badge_id": 92,
      "code": "VERIFIED",
      "name": "Đã xác minh",
      "icon_url": "/storage/badges/verified.svg",
      "type": "scarce",
      "type_name": "Hiếm",
      "is_unlocked": true,
      "is_featured": true,
      "acquired_at": "2026-10-05T09:28:28.000000Z"
    },
    {
      "id": 2,
      "user_badge_id": null,
      "code": "CHAMPION",
      "name": "Quán quân",
      "is_unlocked": false,
      "is_featured": false
    }
  ]
}
```

---

### 3.4. Cập nhật huy hiệu nổi bật (Ghim huy hiệu)
Thiết lập danh sách tối đa 3 huy hiệu nổi bật cho người dùng đang đăng nhập.

* **Endpoint:** `PUT /api/user/badges/featured`
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`application/json`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `user_badge_ids` | array | **Có** | Mảng chứa các `id` trong bảng `user_badges` cần ghim (VD: `[92, 95]`) |

#### Response mẫu (`200 OK`):
```json
{
  "message": "Featured badges updated successfully"
}
```
*(Hệ thống sẽ chặn tự động nếu gửi lên mảng rỗng trong khi user vẫn đang sở hữu huy hiệu, bắt buộc hiển thị ít nhất 1 cái).*

---

## 4. Script Đồng bộ Dữ liệu Cũ (Migration)

Để chuyển đổi đồng bộ từ dữ liệu lịch sử hệ thống cũ (dùng `badge_code` lưu bằng text) sang hệ thống khoá ngoại `badge_id` mới.

Chạy lệnh trên Tinker hoặc dùng một Command riêng biệt:

```bash
php artisan tinker
```

```php
// Đoạn script tự động map và cập nhật ID
$oldRecords = DB::table('user_badges')->whereNull('badge_id')->get();
foreach ($oldRecords as $record) {
    // Tìm badge theo mã code cũ đang lưu (ví dụ cột cũ tên là badge_code)
    $badge = \App\Models\Badge::where('code', $record->badge_code)->first();
    
    if ($badge) {
        DB::table('user_badges')
            ->where('id', $record->id)
            ->update(['badge_id' => $badge->id]);
    }
}
```

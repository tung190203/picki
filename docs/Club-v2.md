# Tài liệu API — Tính năng Thành viên ảo & BXH Thành tích CLB (Sao & Cúp)

Tài liệu này tổng hợp toàn bộ các API được bổ sung, cập nhật và quy tắc nghiệp vụ trong đợt cải tiến Màn CLB V3, tính năng **Thành viên ảo (Club Virtual Members)** và **BXH Thành tích (Sao & Cúp)**.

---

##  Danh sách Endpoints

| STT | Phương thức | Endpoint | Mô tả |
| --- | --- | --- | --- |
| 1 | `GET` | `/api/clubs/{id}/virtual-members` | Lấy danh sách thành viên ảo của CLB |
| 2 | `POST` | `/api/clubs/{id}/virtual-members` | Tạo mới thành viên ảo trong CLB |
| 3 | `DELETE` | `/api/clubs/{id}/virtual-members/{virtualMemberId}` | Xóa mềm thành viên ảo |
| 4 | `GET` | `/api/clubs/{id}/leaderboard` | Lấy Bảng xếp hạng CLB (Điểm trình / Thành tích Sao & Cúp) |
| 5 | `GET` | `/api/mini-tournaments/{id}/candidates` | Tìm kiếm ứng viên tham gia Kèo đấu (tích hợp Thành viên ảo) |
| 6 | `GET` | `/api/tournaments/{id}/candidates` | Tìm kiếm ứng viên tham gia Giải đấu (tích hợp Thành viên ảo) |

---

##  Chi tiết các Endpoints

### 1. Lấy danh sách thành viên ảo của CLB
Lấy danh sách các thành viên ảo thuộc quản lý của CLB.

* **Endpoint:** `GET /api/clubs/{id}/virtual-members`
* **Headers:** `Authorization: Bearer {token}`
* **Query Parameters:**
  * `search` (string, optional): Từ khóa tìm kiếm theo tên.

#### Response mẫu (`200 OK`):
```json
{
  "success": true,
  "message": "Danh sách thành viên ảo",
  "data": [
    {
      "id": 3,
      "club_id": 467,
      "name": "Tôi được tạo trong club",
      "avatar_url": "http://localhost:8000/storage/virtual-avatars/avatar.png",
      "notes": "Thành viên vãng lai chơi thường xuyên",
      "created_by": 33,
      "created_at": "2026-08-24T04:40:12.000000Z"
    }
  ]
}
```

---

### 2. Tạo mới thành viên ảo trong CLB
Tạo một thành viên ảo mới trong CLB (chỉ dành cho Chủ CLB hoặc BQT có quyền).

* **Endpoint:** `POST /api/clubs/{id}/virtual-members`
* **Headers:** `Authorization: Bearer {token}`
* **Request Body (`multipart/form-data` hoặc `application/json`):**

| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| --- | --- | --- | --- |
| `name` | string | **Có** | Tên hiển thị của thành viên ảo (max: 255) |
| `avatar` | file / string | Không | File ảnh avatar upload hoặc URL ảnh |
| `notes` | string | Không | Ghi chú thêm |

#### Response mẫu (`201 Created`):
```json
{
  "success": true,
  "message": "Tạo thành viên ảo thành công",
  "data": {
    "id": 3,
    "club_id": 467,
    "name": "Anh Tuấn Guest",
    "avatar_url": "http://localhost:8000/storage/virtual-avatars/abc.jpg",
    "notes": "Đội hình thứ 7",
    "created_by": 33
  }
}
```

---

### 3. Xóa mềm thành viên ảo
Xóa thành viên ảo khỏi danh sách quản lý của CLB mà **không làm ảnh hưởng hay hỏng lịch sử thi đấu** của các đối thủ/bạn đấu khác trong quá khứ.

* **Endpoint:** `DELETE /api/clubs/{id}/virtual-members/{virtualMemberId}`
* **Headers:** `Authorization: Bearer {token}`

#### Response mẫu (`200 OK`):
```json
{
  "success": true,
  "message": "Đã xóa thành viên ảo"
}
```

---

### 4. Lấy Bảng xếp hạng CLB (Điểm trình & Thành tích ⭐/🏆)
Lấy dữ liệu BXH Điểm trình (dành cho User thật) hoặc BXH Thành tích (⭐ Sao từ Kèo đấu & 🏆 Cúp từ Giải đấu, dành cho cả User thật lẫn Thành viên ảo).

* **Endpoint:** `GET /api/clubs/{id}/leaderboard`
* **Headers:** `Authorization: Bearer {token}`
* **Query Parameters:**

| Tham số | Kiểu dữ liệu | Mặc định | Mới/Cập nhật | Mô tả |
| --- | --- | --- | --- | --- |
| `type` | string | `rating` | `rating` \| `achievement` | Chế độ BXH: `rating` (Điểm trình) hoặc `achievement` (Thành tích) |
| `sub_type` | string | `star` | `star` \| `cup` | Loại thành tích: `star` (⭐ Sao · Kèo đấu) hoặc `cup` (🏆 Cúp · Giải đấu) |
| `time_frame` | string | `month` | `month` \| `quarter` \| `year` \| `all` | Khung thời gian: `month` (Tháng này), `quarter` (Quý này), `year` (Năm này), `all` (Tất cả) |

#### Response mẫu (`200 OK` - `type=achievement&sub_type=star`):
```json
{
  "success": true,
  "message": "Lấy bảng xếp hạng thành tích thành công",
  "data": [
    {
      "rank": 1,
      "user_id": 33,
      "virtual_member_id": null,
      "is_virtual": false,
      "name": "Heino",
      "avatar_url": "http://localhost:8000/storage/avatars/heino.png",
      "gold": 2,
      "silver": 0,
      "bronze": 0,
      "total_points": 6
    },
    {
      "rank": 2,
      "user_id": null,
      "virtual_member_id": 3,
      "is_virtual": true,
      "name": "Tôi được tạo trong club",
      "avatar_url": "http://localhost:8000/storage/virtual-avatars/avatar.png",
      "gold": 0,
      "silver": 2,
      "bronze": 0,
      "total_points": 4
    }
  ]
}
```

---

### 5. Tìm kiếm ứng viên Kèo đấu & Giải đấu (Tích hợp Thành viên ảo)
Khi ứng dụng gửi yêu cầu tìm kiếm ứng viên với phạm vi CLB (`scope=club`), danh sách trả về tự động trộn thêm các **Thành viên ảo** của CLB đó.

* **Endpoints:**
  * Kèo đấu: `GET /api/mini-tournaments/{id}/candidates`
  * Giải đấu: `GET /api/tournaments/{id}/candidates`
* **Query Parameters:**
  * `scope` (string, required): `club` (Trong CLB), `friends` (Bạn bè), `area` (Xung quanh).
  * `club_id` (integer, required khi `scope=club`): ID của CLB.
  * `search` (string, optional): Từ khóa tìm kiếm.

#### Response mẫu (`200 OK`):
```json
{
  "success": true,
  "message": "Danh sách ứng viên",
  "data": [
    {
      "id": 12,
      "name": "Nguyễn Văn A",
      "is_virtual": false,
      "avatar_url": "http://localhost:8000/storage/avatars/a.png"
    },
    {
      "id": null,
      "virtual_member_id": 3,
      "is_virtual": true,
      "is_guest": true,
      "name": "Tôi được tạo trong club",
      "full_name": "Tôi được tạo trong club",
      "avatar_url": "http://localhost:8000/storage/virtual-avatars/avatar.png",
      "gender_text": "Thành viên ảo"
    }
  ]
}
```

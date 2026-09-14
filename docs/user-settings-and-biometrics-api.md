# API Documentation: User Settings & Biometrics Authentication

Tài liệu này hướng dẫn chi tiết về các API mới được phát triển cho tính năng **Đăng nhập bằng Face ID / Vân tay (Passkey / WebAuthn / Mobile Biometrics)** và **Cài đặt người dùng (Theme Dark/Light Mode & Tính năng yêu thích)**.

---

## 📋 Danh sách API Endpoints

| Chức năng | Phương thức | Endpoint | Yêu cầu Token |
|-----------|-------------|----------|---------------|
| Lấy Challenge khởi tạo | `POST` | `/api/auth/biometric/challenge` | ❌ Không |
| Đăng nhập bằng Face ID / Vân tay | `POST` | `/api/auth/biometric/login` | ❌ Không |
| Đăng ký thiết bị Face ID / Vân tay | `POST` | `/api/auth/biometric/register` | ✅ Có (Bearer) |
| Lấy danh sách thiết bị đã đăng ký | `GET` | `/api/auth/biometric/list` | ✅ Có (Bearer) |
| Hủy / Xóa thiết bị Face ID / Vân tay | `DELETE` | `/api/auth/biometric/{id}` | ✅ Có (Bearer) |
| Cập nhật Cài đặt & Theme Mode | `POST` | `/api/user/settings` | ✅ Có (Bearer) |
| Lấy danh sách nhãn hàng tài trợ (Public) | `GET` | `/api/sponsors` (hoặc trong `/api/home`) | ❌ Không |
| Lấy danh sách nhãn hàng tài trợ (Admin) | `GET` | `/api/admin/sponsors` | ✅ Có (Admin) |
| Thêm nhãn hàng tài trợ mới (Admin) | `POST` | `/api/admin/sponsors` | ✅ Có (Admin) |
| Cập nhật nhãn hàng tài trợ (Admin) | `POST` | `/api/admin/sponsors/{id}` | ✅ Có (Admin) |
| Bật / Tắt nhanh hiển thị (Admin) | `PATCH` | `/api/admin/sponsors/{id}/toggle-status` | ✅ Có (Admin) |
| Cập nhật thứ tự nhãn hàng (Admin) | `POST` | `/api/admin/sponsors/reorder` | ✅ Có (Admin) |
| Xóa nhãn hàng tài trợ (Admin) | `DELETE` | `/api/admin/sponsors/{id}` | ✅ Có (Admin) |

---

## 1. 🔑 Biometrics API (Face ID / Touch ID / Vân tay)

### 1.1. Lấy Challenge khởi tạo

**Endpoint:** `POST /api/auth/biometric/challenge`  
**Mô tả:** Tạo chuỗi ngẫu nhiên (challenge hex 32 bytes) dùng để bắt đầu phiên xác thực WebAuthn / Passkey trên thiết bị.

**Request Header:**
```
Content-Type: application/json
Accept: application/json
```

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Biometric challenge generated",
  "data": {
    "challenge": "a1b2c3d4e5f67890123456789abcdef0123456789abcdef0123456789abcdef0"
  }
}
```

---

### 1.2. Đăng ký thiết bị Face ID / Vân tay mới

**Endpoint:** `POST /api/auth/biometric/register`  
**Mô tả:** Liên kết thiết bị hiện tại (Web / iOS / Android) vào tài khoản đang đăng nhập. Mỗi nền tảng của một người dùng sẽ duy trì 1 credential duy nhất.

**Request Header:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "credential_id": "raw_credential_id_from_webauthn_or_native_sdk",
  "public_key": "public_key_string",
  "device_name": "iPhone 15 Pro Max (Face ID)",
  "platform": "ios"
}
```
*Ghi chú parameters:*
- `credential_id` *(string, bắt buộc)*: Định danh Credential ID sinh ra từ thiết bị.
- `public_key` *(string, bắt buộc)*: Khóa công khai dùng để xác thực signature.
- `device_name` *(string, tùy chọn)*: Tên thiết bị hiển thị (nếu bỏ trống hệ thống tự sinh theo `platform`).
- `platform` *(string, tùy chọn)*: Giá trị thuộc `web`, `ios`, `android`. Mặc định `web`.

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Đã đăng ký Face ID / Vân tay thành công cho thiết bị này.",
  "data": {
    "biometric": {
      "id": 1,
      "user_id": 12,
      "credential_id": "raw_credential_id_from_webauthn_or_native_sdk",
      "device_name": "iPhone 15 Pro Max (Face ID)",
      "platform": "ios",
      "last_used_at": "2026-08-26T15:30:00.000000Z",
      "created_at": "2026-08-26T15:30:00.000000Z",
      "updated_at": "2026-08-26T15:30:00.000000Z"
    }
  }
}
```

---

### 1.3. Đăng nhập bằng Face ID / Vân tay

**Endpoint:** `POST /api/auth/biometric/login`  
**Mô tả:** Xác thực và trả về JWT Access Token & Refresh Token bằng `credential_id` mà không cần nhập mật khẩu.

**Request Header:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "credential_id": "raw_credential_id_from_webauthn_or_native_sdk",
  "token": "fcm_device_token_sample",
  "platform": "ios"
}
```

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Đăng nhập thành công bằng Face ID / Vân tay",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1Ni...",
    "refresh_token": "eyJhbGciOiJIUzI1Ni...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 12,
      "full_name": "Nguyễn Văn A",
      "phone": "0987654321",
      "avatar_url": "https://domain.com/avatar.jpg",
      "theme_mode": "dark",
      "settings": {
        "favorite_features": ["bxh_clb", "tao_lich", "thong_bao"]
      }
    }
  }
}
```

**Các phản hồi lỗi thường gặp:**
- **404 Not Found** (Thiết bị chưa đăng ký):
  ```json
  {
    "success": false,
    "message": "Thiết bị này chưa được kích hoạt Face ID / Vân tay. Vui lòng sử dụng mật khẩu để đăng nhập và kích hoạt Face ID / Vân tay.",
    "errors": {
      "status_code": "BIOMETRIC_NOT_REGISTERED"
    }
  }
  ```
- **403 Forbidden** (Tài khoản bị khóa):
  ```json
  {
    "success": false,
    "message": "Tài khoản của bạn đã bị khóa: Vi phạm quy định",
    "errors": {
      "status_code": "USER_BANNED"
    }
  }
  ```

---

### 1.4. Lấy danh sách thiết bị Face ID / Vân tay đã đăng ký

**Endpoint:** `GET /api/auth/biometric/list`  
**Mô tả:** Lấy danh sách các thiết bị đã liên kết với tài khoản người dùng hiện tại để quản lý hoặc hủy thiết bị.

**Request Header:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Danh sách thiết bị Face ID / Vân tay",
  "data": {
    "biometrics": [
      {
        "id": 1,
        "credential_id": "credential_id_1",
        "device_name": "iPhone 15 Pro Max (Face ID)",
        "platform": "ios",
        "last_used_at": "2026-08-26T15:30:00.000000Z",
        "created_at": "2026-08-26T15:20:00.000000Z"
      },
      {
        "id": 2,
        "credential_id": "credential_id_2",
        "device_name": "Web Browser Device (Chrome)",
        "platform": "web",
        "last_used_at": "2025-08-25T10:15:00.000000Z",
        "created_at": "2025-08-25T10:15:00.000000Z"
      }
    ]
  }
}
```

---

### 1.5. Hủy liên kết / Xóa thiết bị Face ID / Vân tay

**Endpoint:** `DELETE /api/auth/biometric/{id}`  
**Mô tả:** Hủy quyền đăng nhập bằng Face ID / Vân tay của thiết bị có ID tương ứng.

**Request Header:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Đã xoá thiết bị khỏi tài khoản.",
  "data": []
}
```

---

## 2. ⚙️ User Settings & Theme Mode API

### 2.1. Cập nhật Cài đặt & Theme Mode

**Endpoint:** `POST /api/user/settings`  
**Mô tả:** Lưu thiết lập giao diện (Sáng / Tối / Theo hệ thống) và danh sách lối tắt tính năng yêu thích của từng tài khoản người dùng lên cơ sở dữ liệu.

**Request Header:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "theme_mode": "dark",
  "favorite_features": ["club", "quick_match", "map", "leaderboard"],
  "settings": {
    "notifications_enabled": true
  }
}
```
*Ghi chú parameters:*
- `theme_mode` *(string, tùy chọn)*: Giá trị hợp lệ: `'light'`, `'dark'`, `'system'`.
- `favorite_features` *(array 4 phần tử, tùy chọn)*: Mảng mã keyword định danh 4 vị trí tính năng yêu thích đã ghim.
- `settings` *(object/array, tùy chọn)*: Cấu hình mở rộng lưu dưới dạng JSON trong Database.

**Response thành công (200 OK):**
```json
{
  "success": true,
  "message": "Đã cập nhật cài đặt người dùng thành công.",
  "data": {
    "id": 12,
    "full_name": "Nguyễn Văn A",
    "email": "nguyenvana@gmail.com",
    "phone": "0987654321",
    "theme_mode": "dark",
    "settings": {
      "favorite_features": ["club", "quick_match", "map", "leaderboard"],
      "notifications_enabled": true
    }
  }
}
```

---

## 3. ⭐ Quy chuẩn Keyword & Đồng bộ Tính Năng Yêu Thích (Ghim Nhanh)

Để đảm bảo tính đồng bộ hoàn toàn giữa **Web Frontend** và **App Mobile (iOS / Android)**, mảng `favorite_features` lưu trong trường `settings` (JSON) của bảng `users` tuân theo quy tắc sau:

### 3.1. Cấu trúc lưu trong Database
- **Tên trường Database:** `users.settings` (`json` column)
- **Đường dẫn JSON:** `user.settings.favorite_features`
- **Kiểu dữ liệu:** `Array<string | null>` (Mảng tối đa 4 phần tử đại diện cho 4 ô ghim trên giao diện).
- **Mặc định khi chưa set:** `["club", "quick_match", "map", "leaderboard"]`

### 3.2. Bảng Tra Cứu Keyword Tính Năng Yêu Thích (Standard Feature Keywords)

| Keyword (`id`) | Tên tính năng | Đường dẫn Web (`route`) | Màn hình tương ứng trên App Mobile |
|----------------|---------------|--------------------------|-----------------------------------|
| `club` | Câu lạc bộ | `/club` | `ClubListScreen` |
| `quick_match` | Tạo trận đấu nhanh | `/quick-match/create` | `CreateQuickMatchScreen` |
| `map` | Tìm sân / Bản đồ | `/map` | `MapSearchScreen` |
| `leaderboard` | Bảng xếp hạng | `/leaderboard` | `LeaderboardScreen` |
| `tournament_create` | Tạo giải đấu | `/tournament/create` | `CreateTournamentScreen` |
| `pairing_wheel` | Vòng quay ghép cặp | `/pairing-wheel` hoặc `/tools/pairing-wheel` | `PairingWheelScreen` |
| `group_draw_wheel` | Vòng quay chia bảng | `/group-draw-wheel` hoặc `/tools/group-draw-wheel` | `GroupDrawWheelScreen` |
| `notifications` | Thông báo | `/notifications` | `NotificationScreen` |
| `settings` | Cài đặt | `/settings` | `SettingsScreen` |
| `profile` | Trang cá nhân | `/profile` | `ProfileScreen` |

---

## 4. 🏷️ Sponsors API (Logo Nhãn Hàng Tài Trợ)

Quản lý và hiển thị logo đối tác, nhãn hàng tài trợ chạy ngang trên trang chủ web và ứng dụng mobile (nằm dưới khung đỏ thông tin điểm/rank và nằm trên mục "Kèo đấu sắp tới"). Hỗ trợ đầy đủ **2 phiên bản logo**: **Logo Dương bản** (nền sáng / Light Mode) và **Logo Âm bản** (nền tối / Dark Mode).

### 4.1. Lấy danh sách nhãn hàng tài trợ (Public)

Có 2 cách để lấy danh sách nhãn hàng đang hoạt động (`is_active = true`):

**Cách 1: Trực tiếp qua endpoint Home (Khuyên dùng)**
- Endpoint: `GET /api/home` hoặc `POST /api/home`
- Dữ liệu nằm trong `data.sponsors`:
```json
{
  "status": true,
  "message": "Lấy dữ liệu thành công",
  "data": {
    "user_info": { ... },
    "settings": {},
    "sponsors": [
      {
        "id": 1,
        "name": "Wilson Pickleball",
        "logo_url": "sponsors/sponsor_pos_1725450000_66d8.png",
        "logo_dark_url": "sponsors/sponsor_neg_1725450000_66d8.png",
        "website_url": "https://www.wilson.com",
        "display_order": 1,
        "is_active": true
      },
      {
        "id": 2,
        "name": null,
        "logo_url": "sponsors/sponsor_pos_1725450001_66d9.png",
        "logo_dark_url": null,
        "website_url": null,
        "display_order": 2,
        "is_active": true
      }
    ]
  }
}
```

**Cách 2: Qua endpoint riêng `GET /api/sponsors`**
- **Endpoint:** `GET /api/sponsors`
- **Yêu cầu Token:** ❌ Không
- **Response thành công (200 OK):**
```json
{
  "status": true,
  "message": "Lấy danh sách nhãn hàng tài trợ thành công",
  "data": [
    {
      "id": 1,
      "name": "Wilson Pickleball",
      "logo_url": "sponsors/sponsor_pos_1725450000_66d8.png",
      "logo_dark_url": "sponsors/sponsor_neg_1725450000_66d8.png",
      "website_url": "https://www.wilson.com",
      "display_order": 1,
      "is_active": true,
      "created_at": "2026-09-04T10:01:07.000000Z",
      "updated_at": "2026-09-11T08:30:00.000000Z"
    }
  ]
}
```

*Mô tả các trường trong Sponsor Object:*
- `id` *(number)*: ID nhãn hàng.
- `name` *(string | null)*: Tên nhãn hàng (Tùy chọn / Optional). Nếu không đặt tên sẽ trả về `null`.
- `logo_url` *(string)*: Đường dẫn ảnh logo dương bản (dành cho nền sáng / Light Mode). Nối với base storage URL: `https://domain.com/storage/{logo_url}`.
- `logo_dark_url` *(string | null)*: Đường dẫn ảnh logo âm bản (dành cho nền tối / Dark Mode). Nếu nhãn hàng chưa thiết lập ảnh âm bản riêng thì trường này trả về `null`. Nối với base storage URL: `https://domain.com/storage/{logo_dark_url}`.
- `website_url` *(string | null)*: Đường dẫn liên kết ngoài app / website của nhà tài trợ (Tùy chọn / Optional). Nếu không có sẽ trả về `null`.
- `display_order` *(number)*: Thứ tự hiển thị ưu tiên (số nhỏ hơn đứng trước).
- `is_active` *(boolean)*: Trạng thái hiển thị (`true` = Đang hiển thị, `false` = Đang ẩn).

---

### 4.2. Quản lý nhãn hàng tài trợ (Admin APIs)

Tất cả các endpoint sau yêu cầu Header: `Authorization: Bearer {admin_access_token}`.

#### 1. Lấy danh sách cho Admin
- **Endpoint:** `GET /api/admin/sponsors`
- **Query Params:**
  - `search` *(string, tùy chọn)*: Tìm kiếm theo tên nhãn hàng hoặc link.
  - `is_active` *(boolean, tùy chọn)*: Lọc theo trạng thái `true` / `false`.
- **Response:** Trả về mảng `sponsors` và thống kê `stats` (tổng số, đang hiển thị, đang ẩn, có link web).

#### 2. Thêm mới nhãn hàng tài trợ
- **Endpoint:** `POST /api/admin/sponsors`
- **Content-Type:** `multipart/form-data`
- **Request Body:**
  - `logo` *(file image, bắt buộc nếu không có logo_url)*: File ảnh logo dương bản / nền sáng (hỗ trợ PNG trong suốt, SVG, JPG, WEBP, tối đa 5MB).
  - `logo_url` *(string, tùy chọn)*: Link ảnh dương bản nếu không upload file.
  - `logo_dark` *(file image, tùy chọn)*: File ảnh logo âm bản / nền tối (Dark Mode) (hỗ trợ PNG trong suốt, SVG, JPG, WEBP, tối đa 5MB).
  - `logo_dark_url` *(string, tùy chọn)*: Link ảnh âm bản nếu không upload file.
  - `name` *(string, tùy chọn)*: Tên nhãn hàng (có thể bỏ trống).
  - `website_url` *(string, tùy chọn)*: Link website ngoài app (vd: `https://brand.com`).
  - `display_order` *(number, tùy chọn)*: Thứ tự hiển thị.
  - `is_active` *(number/boolean, tùy chọn)*: `1` (bật) hoặc `0` (ẩn).

#### 3. Cập nhật nhãn hàng tài trợ
- **Endpoint:** `POST /api/admin/sponsors/{id}`
- **Content-Type:** `multipart/form-data`
- **Request Body:**
  - `logo` *(file image, tùy chọn)*: Tải lên ảnh mới nếu muốn thay đổi logo dương bản.
  - `logo_url` *(string, tùy chọn)*: Link ảnh logo dương bản mới.
  - `logo_dark` *(file image, tùy chọn)*: Tải lên ảnh mới nếu muốn thay đổi logo âm bản.
  - `logo_dark_url` *(string, tùy chọn)*: Link ảnh logo âm bản mới. Gửi chuỗi rỗng `""` nếu muốn gỡ bỏ logo âm bản hiện tại (hệ thống sẽ lưu `null` và tự động xóa file cũ).
  - `name` *(string, tùy chọn)*: Tên nhãn hàng. Nếu muốn xóa tên cũ, gửi chuỗi rỗng `""`, hệ thống sẽ lưu `null`.
  - `website_url` *(string, tùy chọn)*: Link website ngoài app. Nếu muốn xóa link cũ, gửi chuỗi rỗng `""`, hệ thống sẽ lưu `null`.
  - `display_order` *(number, tùy chọn)*: Thứ tự hiển thị.
  - `is_active` *(number/boolean, tùy chọn)*: `1` hoặc `0`.

#### 4. Bật / Tắt nhanh hiển thị
- **Endpoint:** `PATCH /api/admin/sponsors/{id}/toggle-status`
- **Mô tả:** Đảo ngược trạng thái `is_active` giữa hiển thị và ẩn.

#### 5. Sắp xếp thứ tự hàng loạt
- **Endpoint:** `POST /api/admin/sponsors/reorder`
- **Content-Type:** `application/json`
- **Request Body:**
  ```json
  {
    "orders": [
      { "id": 1, "display_order": 1 },
      { "id": 2, "display_order": 2 }
    ]
  }
  ```

#### 6. Xóa nhãn hàng
- **Endpoint:** `DELETE /api/admin/sponsors/{id}`
- **Mô tả:** Xóa nhãn hàng và tự động dọn dẹp các file ảnh logo (cả logo dương bản và âm bản) trên server storage.

---

## 5. 🛠 Hướng dẫn tích hợp cho Frontend & Mobile

### 1. Đăng ký Face ID / Vân tay lần đầu:
1. Người dùng đăng nhập bằng Tài khoản/Mật khẩu hoặc OTP.
2. Tại trang **Cài đặt tài khoản** -> Bật toggle **Face ID / Vân tay**.
3. Frontend gọi `POST /api/auth/biometric/challenge` để lấy challenge.
4. Tương tác với WebAuthn API (`navigator.credentials.create`) trên Web hoặc Native Biometrics SDK trên Mobile.
5. Gửi `credential_id` & `public_key` thu được lên `POST /api/auth/biometric/register`.

### 2. Đăng nhập nhanh ở các lần sau:
1. Mở màn hình Đăng nhập -> Bấm icon **Face ID / Vân tay**.
2. Đọc `credential_id` đã lưu từ Local Secure Storage.
3. Gửi `credential_id` lên `POST /api/auth/biometric/login` để lấy Token khởi tạo phiên làm việc mới.

### 3. Đồng bộ giao diện Dark Mode & Tính năng yêu thích (Web & App):
1. **Khi Đăng nhập thành công:** Đọc `user.theme_mode` và mảng `user.settings.favorite_features` từ API trả về.
2. **Nếu `user.settings.favorite_features` chưa có hoặc rỗng:** Dùng mảng mặc định `["club", "quick_match", "map", "leaderboard"]`.
3. **Khi thay đổi vị trí/ghim tính năng:** Gửi `POST /api/user/settings` với body:
   ```json
   {
     "favorite_features": ["club", "map", "leaderboard", "settings"]
   }
   ```
4. Cả Web và App Mobile sẽ dựa vào mảng Keyword chuẩn ở **Mục 3.2** để hiển thị đúng Icon, Tên và điều hướng màn hình tương ứng.

### 4. Hiển thị Dải Logo Tài Trợ (Sponsors Bar) trên Web & App:
1. **Vị trí hiển thị:**
   - Nằm ngay dưới khung đỏ thông tin điểm/rank (`user_info`) và nằm ngay trên tiêu đề mục `"Kèo đấu sắp tới"`.
2. **Quy tắc hiển thị logo theo Theme (Light Mode vs Dark Mode):**
   - **Khi ở chế độ Sáng (Light Mode):** Hiển thị ảnh `logo_url` (logo dương bản nền sáng).
   - **Khi ở chế độ Tối (Dark Mode):**
     - Nếu nhãn hàng có `logo_dark_url`: Ưu tiên hiển thị `logo_dark_url` (logo âm bản để nổi bật trên nền tối).
     - Nếu `logo_dark_url` là `null`: Fallback hiển thị `logo_url` gốc.
3. **Quy tắc hiển thị số lượng & hiệu ứng:**
   - **Khi chỉ có 1 nhà tài trợ:** Hiển thị đúng 1 logo duy nhất căn giữa, tuyệt đối không lặp lại ảnh.
   - **Khi có từ 2 nhà tài trợ trở lên:** Hiển thị dải chạy ngang liên tục (infinite marquee/slider) lướt nhẹ nhàng.
   - **Không sử dụng viền bọc hay nền thẻ:** Để logo hiển thị trong suốt (transparent) tự nhiên trên nền giao diện. Chiều cao cố định chuẩn mực (khoảng 36px - 44px), chiều dài tự động co giãn theo tỉ lệ gốc (`w-auto`).
   - **Tên nhãn hàng (`name`):** Là tùy chọn (optional). Nếu có thì hiển thị chữ bên cạnh logo, nếu `null` thì chỉ hiển thị ảnh logo.
   - **Liên kết ngoài (`website_url`):** Là tùy chọn (optional). Nếu có link, khi người dùng click/tap vào logo sẽ mở liên kết ngoài app trong trình duyệt mới (`target="_blank"` hoặc InAppBrowser / OpenURL). Nếu không có link thì không bấm được.

### 5. ⚠️ Lưu ý quan trọng về trường `settings` của User (Quy định App Mobile):
- Để tương thích với bộ parser kiểu dữ liệu (Model Decoder/JSON Mapping) trên App Mobile (iOS Swift Decodable, Android Kotlin/Flutter):
- **Trường `settings` khi không có dữ liệu hoặc null:** Server luôn trả về định dạng **JSON Object rỗng `{}`**, tuyệt đối **không** trả về mảng rỗng `[]`.
- Đã được chuẩn hóa đồng bộ tại:
  - `response.data.settings` (trong API `/api/home`)
  - `response.data.user_info.settings` (trong API `/api/home`)
  - `response.data.user.settings` (trong API Auth & `/api/me`)


# Tài Liệu Tích Hợp Chat Realtime Giải Đấu Cho Mobile App (Tournament & Mini Tournament)

> **Phạm vi:** Tài liệu hướng dẫn đội Mobile App (Flutter / React Native / iOS / Android) tích hợp tính năng chat realtime trong giải đấu (Tournament) và giải nhỏ/kèo (Mini Tournament).

---

## 1. Tổng Quan Kiến Trúc

Hệ thống Chat giải đấu kết hợp giữa **REST API** (lưu trữ, lấy lịch sử, upload file) và **WebSocket / Pusher** (nhận tin nhắn realtime tức thì):

```
[ Mobile App ] ──( 1. REST API: Lấy lịch sử tin nhắn )──> [ Backend Laravel ]
[ Mobile App ] ──( 2. WebSocket: Kết nối & Auth kênh )───> [ Pusher Server ]
[ Mobile App ] ──( 3. REST API: Gửi tin nhắn / File )────> [ Backend Laravel ]
                                                                   │
                                                               (broadcast)
                                                                   ▼
[ Các App khác trong giải ] <──( 4. Nhận event realtime )── [ Pusher Server ]
```

* **Backend Base URL:** `https://<host>/api`
* **Xác thực API & WebSocket:** Header `Authorization: Bearer <jwt_token>`

---

## 2. Cấu Hình Kết Nối WebSocket (Pusher)

Mobile App sử dụng SDK Pusher Channels chính thức:
* **Flutter:** `pusher_channels_flutter`
* **React Native:** `@pusher/pusher-websocket-react-native` hoặc `pusher-js`

### Thông số kết nối môi trường:

| Cấu hình | Dev / Staging | Production | Ghi chú |
| :--- | :--- | :--- | :--- |
| **App Key** | `9b7d356da4bc7b91e8b6` | 9b7d356da4bc7b91e8b6 | Key định danh app trên Pusher |
| **Cluster** | `ap1` | `ap1` | Khu vực Asia Pacific (Singapore) |
| **Encrypted (TLS)** | `true` (WSS - port 443) | `true` (WSS - port 443) | Luôn dùng SSL |
| **Auth Endpoint** | `https://<host>/api/broadcasting/auth` | `https://<host>/api/broadcasting/auth` | Endpoint xác thực quyền vào kênh Private |
| **Auth Headers** | `{"Authorization": "Bearer <jwt_token>", "Accept": "application/json"}` | Header bắt buộc để vượt qua auth guard |

---

## 3. Kênh (Channel) & Sự Kiện (Event)

### 3.1. Danh Sách Kênh (Private Channels)

Chat giải đấu bắt buộc sử dụng **Private Channel**. Pusher Client sẽ tự động gọi endpoint `/api/broadcasting/auth` khi gọi subscribe.

| Loại giải | Tên Channel cần Subscribe | Điều kiện cấp quyền (Backend kiểm tra) |
| :--- | :--- | :--- |
| **Giải lớn (Tournament)** | `private-tournament.{tournamentId}` | User là VĐV tham gia thi đấu HOẶC thuộc Ban tổ chức / Trọng tài / Staff của giải |
| **Giải nhỏ (Mini Tournament)** | `private-mini-tournament.{miniTournamentId}` | User là thành viên tham gia mini tournament |

> **Quy ước tên kênh:**
> * Nếu dùng **Pusher SDK gốc (Mobile)**: Tên kênh truyền vào subscribe là `private-tournament.<ID>`.
> * Nếu dùng **Laravel Echo wrapper**: Tự động thêm tiền tố `private-`, chỉ cần gọi `Echo.private("tournament.<ID>")`.

### 3.2. Sự Kiện Cần Lắng Nghe (Event Listeners)

Sau khi subscribe channel thành công, bind vào event tương ứng để nhận tin nhắn mới:

| Loại giải | Tên Event trên Mobile SDK | Tên Event nếu dùng Echo |
| :--- | :--- | :--- |
| **Tournament** | `tournament-sent` | `.tournament-sent` |
| **Mini Tournament** | `mini-tournament-sent` | `.mini-tournament-sent` |

### 3.3. Cấu Trúc Dữ Liệu Tin Nhắn Nhận Được (Event Payload)

Khi có người gửi tin nhắn, Pusher sẽ bắn event với payload sau:

```json
{
  "message": {
    "id": 2045,
    "tournament_id": 12,
    "user_id": 88,
    "user": {
      "id": 88,
      "full_name": "Nguyễn Văn A",
      "avatar_url": "https://dev.picki.vn/storage/avatars/user_88.jpg",
      "rating": 3.75
    },
    "type": "text",
    "content": "Trận tiếp theo sân mấy vậy ban tổ chức?",
    "meta": null,
    "created_at": "2026-10-21 11:00:00",
    "updated_at": "2026-10-21 11:00:00",
    "is_own": false
  }
}
```

> **Xử lý hiển thị nội dung:**
> * `type = "text" | "emoji"`: `content` là nội dung tin nhắn dạng text.
> * `type = "image" | "voice" | "file"`: `content` là đường dẫn file.
>   * Nếu `content` đã là full URL (`http...`) -> Dùng trực tiếp.
>   * Nếu là path tương đối (vd: `chat/abc.jpg`) -> Ghép URL: `https://<host>/storage/` + `content`.
> * `meta`: Object chứa thông tin file `{ "filename": "score_sheet.pdf", "size": 1048576 }`.

---

## 4. Danh Sách REST API

Tất cả request cần header:
```http
Authorization: Bearer <jwt_token>
Accept: application/json
```

---

### 4.1. Lấy Lịch Sử Tin Nhắn

Gọi khi user vừa mở màn hình chat hoặc khi cuộn lên trên để tải thêm tin nhắn cũ (Load more / Pagination).

#### Endpoint:
* **Giải lớn:** `GET /api/send-message/tournament/index/{tournamentId}`
* **Mini Tournament:** `GET /api/send-message/mini-tournament/index/{tournamentId}`

#### Query Parameters:
| Param | Type | Bắt buộc | Mặc định | Mô tả |
| :--- | :---: | :---: | :---: | :--- |
| `page` | integer | ❌ | `1` | Trang hiện tại |
| `per_page` | integer | ❌ | `20` | Số tin nhắn mỗi trang (min: 1, max: 200) |

#### Response Thành Công (200 OK):
```json
{
  "success": true,
  "message": "Lấy tin nhắn thành công",
  "data": [
    {
      "id": 2040,
      "tournament_id": 12,
      "user_id": 55,
      "user": {
        "id": 55,
        "full_name": "Trần Thị B",
        "avatar_url": "https://dev.picki.vn/storage/avatars/55.jpg"
      },
      "type": "text",
      "content": "Chào mn!",
      "meta": null,
      "created_at": "2026-10-21 10:45:10",
      "updated_at": "2026-10-21 10:45:10",
      "is_own": false
    },
    {
      "id": 2045,
      "tournament_id": 12,
      "user_id": 88,
      "user": {
        "id": 88,
        "full_name": "Nguyễn Văn A",
        "avatar_url": "https://dev.picki.vn/storage/avatars/88.jpg"
      },
      "type": "image",
      "content": "chat/qr-map.png",
      "meta": {
        "filename": "qr-map.png",
        "size": 245120
      },
      "created_at": "2026-10-21 11:00:00",
      "updated_at": "2026-10-21 11:00:00",
      "is_own": true
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 92
  }
}
```

---

### 4.2. Gửi Tin Nhắn

Gọi khi người dùng nhấn nút Gửi (text) hoặc gửi file/ảnh/ghi âm.

#### Endpoint:
* **Giải lớn:** `POST /api/send-message/tournament/{tournamentId}`
* **Mini Tournament:** `POST /api/send-message/mini-tournament/{tournamentId}`

#### Định dạng Request:
* **Gửi Text / Emoji:** `Content-Type: application/json`
* **Gửi Kèm File / Ảnh / Voice:** `Content-Type: multipart/form-data`

#### Tham Số Request (Body):
| Trường | Kiểu dữ liệu | Bắt buộc | Mô tả |
| :--- | :---: | :---: | :--- |
| `type` | string | ✅ | Một trong các giá trị: `text`, `image`, `voice`, `emoji`, `file` |
| `content` | string | ❌ | Bắt buộc nếu là `type = text` / `emoji`. Nội dung tin nhắn. |
| `file` | binary file | ❌ | Bắt buộc nếu là `image`, `voice`, `file`. Tối đa 10MB (10240 KB). |

#### Ví Dụ 1: Gửi Tin Nhắn Dạng Text (`application/json`)
```json
{
  "type": "text",
  "content": "Trận chung kết mấy giờ bắt đầu vậy BTC?"
}
```

#### Ví Dụ 2: Gửi Tin Nhắn Dạng Ảnh / File (`multipart/form-data`)
```
FormData:
- type: "image"
- file: (binary data)
```

#### Response Thành Công (200 OK):
```json
{
  "success": true,
  "message": "Gửi tin nhắn thành công",
  "data": {
    "id": 2046,
    "tournament_id": 12,
    "user_id": 88,
    "user": {
      "id": 88,
      "full_name": "Nguyễn Văn A",
      "avatar_url": "https://dev.picki.vn/storage/avatars/88.jpg"
    },
    "type": "text",
    "content": "Trận chung kết mấy giờ bắt đầu vậy BTC?",
    "meta": null,
    "created_at": "2026-10-21 11:05:00",
    "updated_at": "2026-10-21 11:05:00",
    "is_own": true
  }
}
```

---

## 5. Mã Lỗi Thường Gặp (Error Codes)

| Mã HTTP | Tình huống | Message trả về | Hướng xử lý ở App |
| :---: | :--- | :--- | :--- |
| **401** | Chưa đăng nhập hoặc Token hết hạn | `Unauthenticated.` | Refresh Token hoặc đưa user về màn hình Đăng nhập |
| **403** | Không thuộc giải đấu | `Bạn không có quyền gửi tin nhắn vào giải đấu này` / `Bạn không có quyền xem...` | Báo lỗi hoặc ẩn khung chat |
| **404** | ID giải đấu không tồn tại | `No query results for model [Tournament]...` | Kiểm tra lại `tournamentId` |
| **422** | Dữ liệu validate sai | `The type field is required` hoặc file quá 10MB | Kiểm tra input và dung lượng file |

---

## 6. Push Notification Khi Tắt App / Chạy Ngầm (FCM)

Khi người dùng **tắt app hoàn toàn (Terminated)** hoặc **cho app chạy nền (Background)**, kết nối WebSocket (Pusher) sẽ bị ngắt. Lúc này Backend sẽ tự động bắn **FCM Push Notification** đến thiết bị để người dùng nhận được thông báo trên màn hình khóa / thanh thông báo điện thoại.

### 7.1. Cấu Trúc Payload FCM Gửi Về Mobile

Khi có tin nhắn mới, Backend (qua Firebase Cloud Messaging) sẽ gửi payload:

```json
{
  "notification": {
    "title": "[Pickleball Championship 2026] Nguyễn Văn A",
    "body": "Trận tiếp theo sân mấy vậy ban tổ chức?"
  },
  "data": {
    "type": "TOURNAMENT_CHAT",
    "tournament_id": "12",
    "message_id": "2046",
    "sender_id": "88",
    "action": "open_chat"
  },
  "android": {
    "notification": {
      "sound": "noti_sound",
      "channel_id": "picki"
    }
  },
  "apns": {
    "payload": {
      "aps": {
        "sound": "noti_sound.aif",
        "badge": 1
      }
    }
  }
}
```

* Đối với **Mini Tournament**: `data.type` là `"MINI_TOURNAMENT_CHAT"`, kèm `data.mini_tournament_id`.
* Quy tắc hiển thị `body`:
  * Text/Emoji: Hiển thị nội dung tin nhắn.
  * Image: `"Đã gửi một hình ảnh"`.
  * Voice: `"Đã gửi một đoạn ghi âm"`.
  * File: `"Đã gửi một tệp đính kèm"`.

---

### 7.2. API Đồng Bộ Device Token (Bắt Buộc Cho App)

Để Backend biết gửi Push Notification tới thiết bị nào, Mobile App cần gọi API đồng bộ FCM Token:

#### A. Đồng bộ Token khi Đăng nhập / Token Refresh:
```http
POST /api/device-token/sync
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "token": "fcm_token_device_abc123...",
  "platform": "ios" // hoặc "android"
}
```

#### B. Xoá Token khi Đăng xuất (Logout):
```http
DELETE /api/device-token/delete
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "token": "fcm_token_device_abc123..."
}
```

---

### 7.3. Xử Lý Điều Hướng Khi Bấm Thông Báo (Deep Linking)

* **Trường hợp 1: Người dùng chạm vào thông báo ngoài màn hình khóa (App mở từ Background/Terminated):**
  1. Đọc payload trong `data`.
  2. Nếu `data.action === "open_chat"`:
     - Nếu `data.type === "TOURNAMENT_CHAT"`: Lấy `tournament_id = data.tournament_id`, điều hướng thẳng vào màn hình chat giải đấu đó.
     - Nếu `data.type === "MINI_TOURNAMENT_CHAT"`: Lấy `mini_tournament_id = data.mini_tournament_id`, điều hướng vào màn hình chat mini tournament đó.
* **Trường hợp 2: Người dùng đang mở App (Foreground):**
  - Nếu người dùng **đang đứng trong đúng màn hình chat giải đấu đó**: Bỏ qua thông báo FCM (vì Pusher WebSocket đã nhận và hiển thị tin nhắn rồi).
  - Nếu người dùng đang đứng ở màn hình khác (Trang chủ, Lịch đấu, Hồ sơ...): Hiển thị in-app banner thông báo để người dùng bấm vào chuyển nhanh tới phòng chat.

---

## 7. Các Lưu Ý Kỹ Thuật Quan Trọng

1. **Tránh trùng lặp tin nhắn (Duplicate Message):**
   * Khi user bấm Gửi, app gọi API `POST /api/send-message/tournament/{id}`.
   * Backend dùng `broadcast(...)->toOthers()`. Nếu mobile app có gửi header `X-Socket-ID: <socket_id>` (lấy từ Pusher instance) kèm theo request POST, Pusher sẽ tự loại trừ không bắn event về chính máy người gửi.
   * Nếu App không truyền header `X-Socket-ID`, App cần tự lọc tin nhắn dựa theo `message.id` hoặc so sánh `message.user_id === currentUserId` để tránh hiển thị 2 lần.
2. **Quản lý Vòng đời (Lifecycle):**
   * Chỉ `subscribe` khi user vào màn hình Chat giải đấu.
   * Bắt buộc gọi `unsubscribe` khi rời màn hình (đóng modal / pop navigation) để tiết kiệm pin, dữ liệu mạng và giới hạn kết nối đồng thời của gói Pusher.
3. **Hiển thị File / Ảnh:**
   * Khi render ảnh/audio/file, cần kiểm tra prefix của `content`: Nếu chưa có domain (ví dụ `chat/xyz.jpg`), hãy nối với URL storage: `https://<host>/storage/${content}`.
4. **Cấu hình Test Push Notification trên môi trường Dev / Staging:**
   * Mặc định, listener Push Notification chỉ gửi trên `production` để tránh spam token rác khi dev.
   * Nếu muốn test Push Notification trên môi trường `dev` hoặc `staging`, chỉ cần thêm cấu hình vào `.env`:
     ```env
     ENABLE_PUSH_NON_PROD=true
     ```
   * Đảm bảo tiến trình Queue Worker đang chạy:
     ```bash
     php artisan queue:work --tries=3
     ```

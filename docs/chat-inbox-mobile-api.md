# Tài Liệu Tích Hợp API Hệ Thống Chat & Hộp Thư Hợp Nhất Cho Mobile App

> **Dành cho:** Mobile App Developers (Flutter / React Native / iOS / Android) & Frontend Engineers  
> **Phiên bản API:** v1.0  
> **Base URL:** `https://<domain>/api`  
> **Xác thực:** Bearer Token (`Authorization: Bearer <jwt_or_sanctum_token>`)  
> **WebSocket:** Pusher Channels (`cluster: ap1`, WSS port 443)  

---

## 1. Tổng Quan Kiến Trúc & Các Chức Năng Hiện Có

Hệ thống Chat & Hộp thư (Unified Inbox) giải quyết toàn bộ bài toán liên lạc, thông báo và tương tác mạng xã hội pickleball:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            HỆ THỐNG UNIFIED INBOX                           │
├─────────────────────────────────────────────────────────────────────────────┤
│  [Tab Tất cả]        Trộn thông báo hệ thống và tin nhắn cá nhân theo giờ    │
│  [Tab Tin nhắn]      Cuộc trò chuyện bạn bè / người quen / nhóm CLB & Kèo   │
│  [Thẻ Người lạ]      Hàng đợi tin nhắn từ người lạ (Strangers Queue)        │
│  [Tab Thông báo]     Thông báo giải đấu, kết quả trận, quản trị sân, v.v.    │
└─────────────────────────────────────┬───────────────────────────────────────┘
                                      │
┌─────────────────────────────────────▼───────────────────────────────────────┐
│                          TÍNH NĂNG CHAT CHI TIẾT                            │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. Chat 1-1 (Direct Message) & Chat Nhóm (CLB, Kèo đấu, Giải đấu)           │
│ 2. Realtime WebSocket (Pusher/Echo) + FCM Push Notification khi app đóng     │
│ 3. Đánh dấu Đã đọc / Chưa đọc realtime (Seen / Last Read / Unread Counter)  │
│ 4. Phân loại Người lạ (Stranger) & Giới hạn chống Spam (Tối đa 10 người/ngày)│
│ 5. Đính kèm Thẻ tương tác (Kèo đấu, Giải đấu, Người chơi, CLB)              │
│ 6. Chặn (Block) & Bỏ chặn (Unblock) người dùng linh hoạt                     │
│ 7. Báo cáo quấy rối (Report Chat / Message)                                 │
│ 8. Tự động lưu trữ (Auto-archive) sau 30 ngày không hoạt động                │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Thiết Lập Realtime WebSocket (Pusher / Echo)

Mobile App kết nối đến Pusher Channels để nhận cập nhật tin nhắn và trạng thái hộp thư theo thời gian thực.

### 2.1. Thông số kết nối

| Tham số | Giá trị | Ghi chú |
| :--- | :--- | :--- |
| **App Key** | `9b7d356da4bc7b91e8b6` | Key Pusher môi trường |
| **Cluster** | `ap1` | Asia Pacific (Singapore) |
| **TLS/WSS** | `true` | Bắt buộc SSL cổng 443 |
| **Auth Endpoint** | `https://<domain>/api/broadcasting/auth` | Xác thực quyền vào kênh Private |
| **Auth Headers** | `Authorization: Bearer <token>`<br>`Accept: application/json` | Header bắt buộc gửi lên khi auth kênh |

### 2.2. Danh Sách Kênh (Channels) & Sự Kiện (Events)

#### Kênh 1: Cập nhật Hộp thư cá nhân (`private-user.{userId}`)
Mỗi user khi đăng nhập app sẽ subscribe kênh này để nhận cập nhật tin nhắn mới, nhảy số badge đỏ unread trên tabbar:

* **Tên channel:** `private-user.{userId}`
* **Sự kiện lắng nghe:** `.conversation.updated` (hoặc `ConversationUpdated`)
* **Payload nhận được:**
```json
{
  "conversation": {
    "id": 15,
    "type": "dm",
    "title": "Nguyễn Văn A",
    "avatar_url": "https://<domain>/storage/avatars/user_1.jpg",
    "unread_count": 3,
    "last_message": {
      "id": 105,
      "content": "Chiều nay ra sân lúc 5h nhé!",
      "type": "text",
      "created_at": "2026-10-22 15:30:00"
    },
    "last_message_at": "2026-10-22 15:30:00"
  },
  "unread_count": 3
}
```

#### Kênh 2: Phòng Chat chi tiết (`private-chat.room.{conversationId}`)
Khi người dùng mở một phòng chat cụ thể, app subscribe kênh này:

* **Tên channel:** `private-chat.room.{conversationId}`
* **Sự kiện 1: Nhận tin nhắn mới** `.message.sent` (hoặc `ChatMessageSent`)
```json
{
  "message": {
    "id": 201,
    "conversation_id": 15,
    "user_id": 88,
    "user": {
      "id": 88,
      "full_name": "Nguyễn Văn A",
      "avatar_url": "https://<domain>/storage/avatars/u88.jpg"
    },
    "type": "text",
    "content": "Sân số 3 nhé mọi người",
    "card_data": null,
    "created_at": "2026-10-22 15:32:00"
  }
}
```

* **Sự kiện 2: Trạng thái đã xem** `.messages.read` (hoặc `MessagesRead`)
Phát ra khi đối phương vừa đọc tin nhắn. App cập nhật icon "Đã xem" (seen tick).
```json
{
  "conversation_id": 15,
  "reader_id": 88,
  "last_read_message_id": 201
}
```

> **LƯU Ý QUAN TRỌNG VỀ `X-Socket-ID`:**  
> Khi gửi tin nhắn qua REST API (`POST /messages`), Mobile App nên lấy socket ID hiện tại từ Pusher client (`pusher.getSocketId()`) và đính kèm vào HTTP Header `X-Socket-ID: <socket_id>`. Backend sẽ bỏ qua (không broadcast ngược lại) cho chính máy gửi, tránh giật lag hoặc duplicate tin nhắn!

---

## 3. Danh Sách API Hộp Thư (Unified Inbox)

### 3.1. Lấy danh sách Inbox chính
`GET /api/inbox`

**Query Parameters:**
* `tab`: `all` (mặc định), `messages` (chỉ tin nhắn người quen), `alerts` (chỉ thông báo hệ thống).
* `is_unread`: `1` hoặc `true` để lọc riêng tin chưa đọc.
* `q`: Từ khóa tìm kiếm theo tab tương ứng (tên cuộc trò chuyện, tên người dùng, tên Kèo/Giải/CLB, nội dung tin nhắn hoặc thông báo).
* `page`: Số trang (mặc định 1).
* `per_page`: Số lượng mỗi trang (mặc định 20, max 100).

**Response (tab = messages):**
```json
{
  "status": true,
  "message": "Lấy danh sách inbox thành công",
  "data": {
    "tab": "messages",
    "strangers_summary": {
      "unread_count": 2,
      "total_conversations": 3,
      "latest_sender_name": "Trần Thị B",
      "latest_message": "Chào bạn, cho mình hỏi về kèo đấu..."
    },
    "items": [
      {
        "id": 15,
        "type": "dm",
        "title": "Nguyễn Văn A",
        "avatar_url": "https://<domain>/storage/avatars/u88.jpg",
        "context_tag": "Cùng CLB Tứ Liên Pickleball",
        "is_stranger": false,
        "unread_count": 0,
        "is_muted": false,
        "is_archived": false,
        "is_blocked_by_me": false,
        "is_blocked_by_other": false,
        "is_blocked": false,
        "last_message": {
          "id": 201,
          "content": "Chiều nay ra sân lúc 5h nhé!",
          "type": "text",
          "created_at": "2026-10-22 15:30:00"
        },
        "last_message_at": "2026-10-22 15:30:00",
        "other_user": {
          "id": 88,
          "full_name": "Nguyễn Văn A",
          "avatar_url": "https://<domain>/storage/avatars/u88.jpg",
          "rating": 3.5
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1
    }
  }
}
```

### 3.2. Lấy số lượng chưa đọc (Unread Badges)
`GET /api/inbox/unread-count`

Dùng để cập nhật badge số đỏ trên Navigation Bar / Tabbar của Mobile App.

**Response:**
```json
{
  "status": true,
  "message": "Lấy số lượng chưa đọc thành công",
  "data": {
    "total_unread": 5,
    "messages_unread": 2,
    "alerts_unread": 3,
    "strangers_count": 1
  }
}
```

### 3.3. Hàng đợi tin nhắn từ Người lạ (Strangers Queue)
`GET /api/inbox/strangers`

Hiển thị danh sách các cuộc trò chuyện từ những người dùng chưa từng quen biết gửi đến.

**Query Parameters:**
* `q`: Từ khóa tìm kiếm theo tên người gửi hoặc nội dung tin nhắn.
* `page`: Số trang (mặc định 1).
* `per_page`: Số lượng mỗi trang (mặc định 20, max 100).

**Response:**
```json
{
  "status": true,
  "message": "Lấy danh sách tin nhắn người lạ thành công",
  "data": {
    "items": [
      {
        "id": 18,
        "type": "dm",
        "title": "Hoàng Nam",
        "avatar_url": "https://<domain>/storage/avatars/u99.jpg",
        "context_tag": "Không có điểm chung — tìm thấy qua Tìm kiếm",
        "is_stranger": true,
        "unread_count": 1,
        "last_message": {
          "id": 205,
          "content": "Bạn có tham gia kèo sáng mai không?",
          "type": "text",
          "created_at": "2026-10-22 14:10:00"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1
    }
  }
}
```

### 3.4. Đánh dấu đã đọc tất cả
`POST /api/inbox/read-all`

**Request Body:**
```json
{
  "tab": "all" // "all" | "messages" | "strangers" | "alerts"
}
```

---

## 4. Danh Sách API Phòng Chat & Tin Nhắn

### 4.1. Mở hoặc Tạo cuộc trò chuyện 1-1 (DM)
`POST /api/chat/conversations/dm`

Dùng khi bấm "Nhắn tin" từ trang Profile hoặc từ thẻ Người chơi.

**Request Body:**
```json
{
  "receiver_id": 88
}
```

**Response:** Trả về đối tượng `ConversationResource` (kèm cờ `is_blocked_by_me`, `is_blocked_by_other`, `is_stranger`).

### 4.2. Mở hoặc Đồng bộ cuộc trò chuyện Nhóm
`POST /api/chat/conversations/group`

Dùng khi bấm vào Chat CLB, Chat Kèo đấu, hoặc Chat Giải đấu. Hệ thống tự động đồng bộ thành viên theo vai trò.

**Request Body:**
```json
{
  "type": "club", // "club" | "mini_tournament" | "tournament"
  "target_id": 12
}
```

### 4.3. Lấy thông tin chi tiết cuộc trò chuyện
`GET /api/chat/conversations/{id}`

### 4.4. Lấy lịch sử tin nhắn (Phân trang cuộn lên)
`GET /api/chat/conversations/{id}/messages`

**Query Parameters:**
* `page`: Số trang (mặc định 1)
* `per_page`: Số lượng tin mỗi lần tải (mặc định 30, max 100)
* `before_id`: Lấy các tin nhắn trước ID này (hỗ trợ infinite scroll cuộn lên mượt mà)

**Response:**
```json
{
  "status": true,
  "message": "Lấy danh sách tin nhắn thành công",
  "data": [
    {
      "id": 105,
      "conversation_id": 15,
      "user_id": 88,
      "user": {
        "id": 88,
        "full_name": "Nguyễn Văn A",
        "avatar_url": "https://<domain>/storage/avatars/u88.jpg",
        "rating": 3.75
      },
      "type": "text", // "text" | "card_mini_tournament" | "card_tournament" | "card_player" | "card_club" | "image" | "file"
      "content": "Mình gửi bạn thông tin kèo tối nay nhé",
      "metadata": null,
      "card_data": null,
      "is_own": false,
      "status": "seen", // "sent" | "seen"
      "created_at": "2026-10-22 15:30:00"
    },
    {
      "id": 106,
      "conversation_id": 15,
      "user_id": 88,
      "type": "card_mini_tournament",
      "content": "Đã chia sẻ: Kèo đôi nam 3.0 tối T4",
      "metadata": {
        "target_id": 1061
      },
      "card_data": {
        "id": 1061,
        "is_deleted": false,
        "title": "Kèo đôi nam 3.0 tối T4",
        "poster_url": "https://<domain>/storage/posters/poster_1061.jpg",
        "start_time": "2026-10-24 19:00:00",
        "location_name": "Sân Pickleball Cầu Giấy",
        "slots_text": "3/4 người tham gia",
        "status_text": "Đang mở đăng ký",
        "route": "/mini-tournament-detail/1061"
      },
      "is_own": false,
      "status": "seen",
      "created_at": "2026-10-22 15:31:00"
    }
  ]
}
```

### 4.5. Gửi tin nhắn vào cuộc trò chuyện
`POST /api/chat/conversations/{id}/messages`

**Headers khuyến nghị:** `X-Socket-ID: <pusher_socket_id>`

#### Gửi tin nhắn Text:
```json
{
  "type": "text",
  "content": "Chào bạn, mình vừa đến sân rồi nhé!"
}
```

#### Gửi thẻ Kèo đấu (`card_mini_tournament`):
*(Hệ thống vẫn hỗ trợ alias cũ `card_match` nếu cần tương thích ngược)*
```json
{
  "type": "card_mini_tournament",
  "content": "Đã chia sẻ: Kèo giao lưu tối thứ 6",
  "metadata": {
    "target_id": 1061
  }
}
```

#### Gửi thẻ Giải đấu (`card_tournament`):
```json
{
  "type": "card_tournament",
  "content": "Đã chia sẻ: Giải Pickleball Hà Nội Open 2026",
  "metadata": {
    "target_id": 85
  }
}
```

#### Gửi thẻ Người chơi (`card_player`):
```json
{
  "type": "card_player",
  "content": "Đã chia sẻ: VĐV Trần Minh",
  "metadata": {
    "target_id": 120
  }
}
```

#### Gửi thẻ Câu lạc bộ (`card_club`):
```json
{
  "type": "card_club",
  "content": "Đã chia sẻ: CLB Tứ Liên Pickleball",
  "metadata": {
    "target_id": 8
  }
}
```

### 4.6. Đánh dấu đã đọc cuộc trò chuyện
`POST /api/chat/conversations/{id}/read`

Gọi khi người dùng bấm mở màn hình chat. Backend sẽ tự động reset `unread_count = 0` và bắn event `.messages.read` sang cho đối phương.

### 4.7. Bật / Tắt thông báo cuộc trò chuyện (Mute / Unmute)
`POST /api/chat/conversations/{id}/mute`

Dùng để bật hoặc tắt thông báo đẩy (FCM Push Notification) của một cuộc trò chuyện.

**Request Body (optional):**
```json
{
  "is_muted": true
}
```
*(Nếu không truyền `is_muted`, hệ thống sẽ tự động đảo trạng thái hiện tại `true <-> false`)*

**Response:** Trả về đối tượng `ConversationResource` đã cập nhật cờ `is_muted`.

---

## 5. API Đính Kèm Thẻ Tương Tác (Shortcut Picker)

Mobile App hiển thị popup chọn thẻ đính kèm (gồm 4 tabs) thông qua các endpoint lọc thông minh sau:

| Endpoint | Mô tả | Quy tắc bảo mật dữ liệu |
| :--- | :--- | :--- |
| `GET /api/chat/shortcuts/matches?q=...` | Danh sách Kèo đấu gần đây | Chỉ hiển thị kèo công khai hoặc kèo riêng tư mà chính user đã tham gia/tạo |
| `GET /api/chat/shortcuts/tournaments?q=...` | Danh sách Giải đấu gần đây | Chỉ hiển thị giải công khai hoặc giải user đang thi đấu |
| `GET /api/chat/shortcuts/players?q=...` | Danh sách Người chơi | Tự động loại bỏ user bị ban, user ẩn quyền riêng tư (`visibility = private`) |
| `GET /api/chat/shortcuts/clubs?q=...` | Danh sách CLB của tôi | Danh sách các CLB mà user đang là thành viên chính thức (`active`) |

---

## 6. API Chặn, Bỏ Chặn & Báo Cáo Người Dùng

### 6.1. Chặn người dùng (Block)
`POST /api/chat/users/{userId}/block`

**Request Body (optional):**
```json
{
  "reason": "Spam tin nhắn"
}
```
* **Cơ chế:** Khi bị chặn, cuộc trò chuyện **vẫn được giữ lại** trong danh sách tin nhắn. 
* Người chặn mở chat sẽ thấy banner: *"Bạn đã chặn người dùng này. Bỏ chặn để tiếp tục trò chuyện"* kèm nút **[Bỏ chặn]**.
* Người bị chặn mở chat sẽ thấy thông báo: *"Bạn không thể gửi tin nhắn cho người dùng này"*.

### 6.2. Bỏ chặn người dùng (Unblock)
`DELETE /api/chat/users/{userId}/unblock`

* Khôi phục ngay quyền nhắn tin cho cả 2 bên.

### 6.3. Lấy danh sách những người đã chặn
`GET /api/chat/users/blocks`

**Response:**
```json
{
  "status": true,
  "message": "Lấy danh sách chặn thành công",
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "blocked_user_id": 88,
      "created_at": "2026-10-22 15:40:00",
      "blocked_user": {
        "id": 88,
        "full_name": "Nguyễn Văn A",
        "avatar_url": "https://<domain>/storage/avatars/u88.jpg"
      }
    }
  ]
}
```

### 6.4. Báo cáo quấy rối (Report)
`POST /api/chat/reports`

**Request Body:**
```json
{
  "reported_user_id": 88,
  "conversation_id": 15, // nullable
  "message_id": 201,      // nullable
  "reason": "Quấy rối, spam quảng cáo",
  "details": "Người này liên tục gửi tin nhắn rác vào ban đêm"
}
```

---

## 7. Các Quy Tắc Nghiệp Vụ (Business Logic) Quan Trọng

1. **Cơ chế chuyển đổi "Người lạ" -> "Hộp thư chính":**
   * Nếu User A nhắn tin cho User B (2 người chưa có liên hệ chung): Cuộc trò chuyện nằm ở `is_stranger = true` của User B.
   * Khi User B bấm gửi bất kỳ 1 tin nhắn phản hồi (reply) nào: Hệ thống **vĩnh viễn** gỡ cờ `is_stranger = false` cho cả 2 người, cuộc trò chuyện lập tức hiển thị trên Hộp thư chính `messages`.
2. **Hạn mức chống Spam người lạ:**
   * Mỗi tài khoản chỉ được phép nhắn tin mở đầu tới tối đa **10 người lạ mỗi ngày**.
   * Hạn ngạch tự động reset về 0 lúc 00:00 mỗi ngày.
   * Nếu vượt quá hạn mức, API trả về mã lỗi `400` với thông báo cụ thể.
3. **Quy tắc chia sẻ thẻ tương tác:**
   * Kèo đấu hoặc Giải đấu được cài đặt Riêng tư (`is_private = true`): Chỉ người đã tham gia mới được chia sẻ.
   * Người dùng cài đặt quyền riêng tư `private`: Không thể gửi thẻ profile của họ cho người khác.
4. **Xử lý URL hình ảnh (Poster / Avatar):**
   * Nếu URL bắt đầu bằng `http://` hoặc `https://`: Sử dụng trực tiếp.
   * Nếu là path lưu trữ nội bộ (bắt đầu bằng `/storage/` hoặc relative path như `tournaments/posters/...`): Ghép thêm Base URL: `https://<domain>/storage/<path>`.
5. **FCM Push Notification Payload:**
   Khi có tin nhắn mới mà app đang chạy nền (background) hoặc đã tắt, hệ thống sẽ đẩy Push Notification qua Firebase với payload:
   ```json
   {
     "data": {
       "type": "CHAT_MESSAGE",
       "conversation_id": "15",
       "conversation_type": "dm",
       "is_stranger": "0",
       "action": "open_chat",
       "message_id": "201"
     }
   }
   ```
   Mobile App bắt sự kiện click thông báo để điều hướng (navigate) mở ngay phòng chat tương ứng!

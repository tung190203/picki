# API: Mini Match Features

## 1. Gán Sân vào MiniMatch

### Mô tả

Cho phép gán số sân (court number) vào các trận đấu nhỏ (mini match) trong giải đấu.

### API Endpoints

#### Tạo MiniMatch với Court Number

**POST** `/api/v1/mini-tournaments/{miniTournamentId}/mini-matches`

**Request Body:**
```json
{
    "name": "Trận đấu 1",
    "team1": [1, 2],
    "team2": [3, 4],
    "court_number": 1,
    "round_number": 1
}
```

#### Cập nhật MiniMatch (chỉnh sân)

**PUT** `/api/v1/mini-tournaments/{miniTournamentId}/mini-matches/{matchId}`

**Request Body:**
```json
{
    "name": "Trận đấu 1 - Sân 2",
    "court_number": 2
}
```

#### Lấy danh sách MiniMatches (có hỗ trợ lọc theo sân)

**GET** `/api/v1/mini-tournaments/{miniTournamentId}/mini-matches`

**Query Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| court_number | integer | No | Lọc trận theo số sân. Chỉ áp dụng cho thể thức tiêu chuẩn (standard). |

**Request Example:**
```
GET /api/v1/mini-tournaments/123/mini-matches?court_number=2
```

**Response:**
```json
{
    "success": true,
    "message": "Lấy danh sách trận đấu thành công",
    "data": {
        "matches": [
            {
                "id": 1,
                "name": "Trận đấu 1",
                "court_number": 2,
                "status": "pending",
                "team1": {...},
                "team2": {...}
            }
        ],
        "rounds": [],
        "match_format": "standard",
        "total_matches": 5,
        "confirmed_matches": 2
    }
}
```

#### Lấy danh sách sân đã được gán

**GET** `/api/v1/mini-tournaments/{miniTournamentId}/mini-matches/assigned-courts`

**Description:**
Trả về danh sách các `court_number` đã được gán cho trận trong kèo.

**Response:**
```json
{
    "success": true,
    "message": "Lấy danh sách sân đã gán thành công",
    "data": [1, 2, 3, 5]
}
```

## 2. Modify Avatar cho MiniParticipant

### API Endpoint

#### Modify Avatar

**POST** `/api/v1/mini-tournaments/{miniTournamentId}/participants/{participantId}/modify-avatar`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Request Body:**
```
avatar: (image file) required, image|mimes:jpeg,png,jpg,gif|max:2048
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Avatar đã được cập nhật",
    "data": {
        "participant_id": 123,
        "modified_avatar": "https://example.com/storage/modified_avatars/abc123_1724567890.jpg"
    }
}
```

**Error Responses:**

| Status | Message |
|--------|---------|
| 400 | Validation error (file not image or too large) |
| 403 | Bạn không có quyền thực hiện thao tác này |
| 404 | Participant không tồn tại trong giải đấu này |

### Resource Response

#### MiniParticipantResource.php
Avatar đã modify được trả về trong API participant:

```json
{
    "id": 123,
    "name": "Nguyễn Văn A",
    "avatar": "https://example.com/storage/avatars/user.jpg",
    "modified_avatar": "https://example.com/storage/modified_avatars/abc123.jpg",
    ...
}
```
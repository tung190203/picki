# Score Verification API Documentation

## Base URL
```
/api
```

---

## User Endpoints (Authenticated)

### 1. Create Score Verification Request

**POST** `/score-verifications`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `score_type` | string | Yes | `SPCN` or `DUPR` |
| `score` | number | Yes | Score value (0 - 8) |
| `image` | file | Yes | Image file (jpeg, png, jpg, gif; max 5MB) |

**Success Response (201):**
```json
{
    "success": true,
    "message": "Yêu cầu xác minh đã được gửi",
    "data": {
        "id": 1,
        "request_number": "SV-20260717-000001",
        "score_type": "SPCN",
        "submitted_score": "2.000",
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

**Error Response (409 - Pending request exists):**
```json
{
    "success": false,
    "message": "Bạn đang có yêu cầu đang chờ duyệt",
    "errors": { "code": "PENDING_REQUEST_EXISTS" },
    "code": 409
}
```

**Error Response (422 - Validation failed):**
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "score_type": ["Vui lòng chọn loại điểm."],
        "score": ["Vui lòng nhập điểm."],
        "image": ["Vui lòng tải lên ảnh chứng minh."]
    }
}
```

---

### 2. Get Latest Request

**GET** `/score-verifications/latest`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "request_number": "SV-20260717-000001",
        "score_type": "SPCN",
        "submitted_score": "2.000",
        "current_picki_score": "1.448",
        "difference": 0.552,
        "threshold": 0.5,
        "is_over_threshold": true,
        "status": "PENDING",
        "created_at": "2026-07-17T12:00:00Z",
        "is_new": true
    }
}
```

**No Request Exists (200):**
```json
{
    "success": true,
    "data": null
}
```

---

### 3. Get Verification History (Future)

**GET** `/score-verifications`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | - | `PENDING`, `APPROVED`, or `REJECTED` |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (max 100) |

**Success Response (200):**
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

## Admin Endpoints

**Headers:**
```
Authorization: Bearer {token}
X-User-Role: super_admin
```

---

### 4. Dashboard & List Requests

**GET** `/admin/score-verifications`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | `PENDING` | `PENDING`, `APPROVED`, or `REJECTED` |
| `score_type` | string | - | `SPCN` or `DUPR` |
| `keyword` | string | - | Search by user name |
| `from_date` | date | - | Filter from date (YYYY-MM-DD) |
| `to_date` | date | - | Filter to date (YYYY-MM-DD) |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (max 100) |

**Example:**
```
GET /admin/score-verifications?status=PENDING&score_type=SPCN&page=1&per_page=20
```

**Success Response (200):**
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

### 5. Get Request Detail

**GET** `/admin/score-verifications/{verification}`

**Success Response (200):**
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

### 6. Approve Request

**POST** `/admin/score-verifications/{verification}/approve`

**Request Body:**
```json
{
    "award_anchor_badge": true
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `award_anchor_badge` | boolean | No | Award Anchor badge to user (default: false) |

**Success Response (200):**
```json
{
    "success": true,
    "message": "Đã duyệt yêu cầu",
    "data": {
        "status": "APPROVED"
    }
}
```

**Error Response (400 - Already processed):**
```json
{
    "success": false,
    "message": "Request has already been processed",
    "code": 400
}
```

---

### 7. Reject Request

**POST** `/admin/score-verifications/{verification}/reject`

**Request Body:**
```json
{
    "reason": "Điểm không khớp với hồ sơ"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | Yes | Rejection reason (max 500 chars) |

**Success Response (200):**
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

## Route Summary

```
# User Routes (Authenticated)
POST   /score-verifications                  - Create request
GET    /score-verifications/latest           - Get latest request
GET    /score-verifications                  - Get history (future)

# Admin Routes (super_admin)
GET    /admin/score-verifications           - Dashboard + List
GET    /admin/score-verifications/{id}       - Detail
POST   /admin/score-verifications/{id}/approve - Approve
POST   /admin/score-verifications/{id}/reject  - Reject
```

---

## Response Fields Description

| Field | Description |
|-------|-------------|
| `request_number` | Unique request ID (format: `SV-YYYYMMDD-NNNNNN`) |
| `current_picki_score` | User's current score from `users.self_score` |
| `difference` | Absolute difference between submitted and current score |
| `threshold` | Max allowed difference (from config, default 0.5) |
| `is_over_threshold` | `true` if difference > threshold |
| `is_new` | `true` if request is less than 24 hours old |

---

## Status Values

| Status | Description |
|--------|-------------|
| `PENDING` | Chờ duyệt |
| `APPROVED` | Đã duyệt |
| `REJECTED` | Từ chối |

---

## Score Types

| Type | Description |
|------|-------------|
| `SPCN` | Điểm SPCN |
| `DUPR` | Điểm DUPR |

---

## Controller Structure

| Controller | Namespace | Description |
|------------|-----------|-------------|
| `ScoreVerificationController` | `App\Http\Controllers\` | User endpoints |
| `ScoreVerificationManagementController` | `App\Http\Controllers\Admin\` | Admin endpoints |

# MiniTournament Matches API — Documentation

## 1. API `GET /api/mini-matches/index/{miniTournamentId}`

Lấy danh sách trận đấu. Response khác nhau tùy `match_format`.

### Response structure chung (metadata — luôn có)

```json
{
  "data": {
    "match_format": "mixed_gender",
    "is_session_started": true,
    "session_status": "ongoing",
    "total_matches": 12,
    "confirmed_matches": 5,
    "current_round": 2
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 12,
    "total": 12
  },
  "message": "Lấy danh sách trận đấu thành công"
}
```

| Field | Kiểu | Mô tả |
|---|---|---|
| `match_format` | string?null | `standard`, `partner_rotation`, `mixed_gender`, `rank_pairing` |
| `is_session_started` | bool | Đã bắt đầu session hay chưa |
| `session_status` | string?null | `pending_group`, `ready`, `ongoing`, `finished` |
| `total_matches` | int | Tổng số trận |
| `confirmed_matches` | int | Số trận đã hoàn thành (`status = completed`) |
| `current_round` | int?null | Round đang active (chỉ có khi `match_format` là 3 format kia) |

---

### Trường hợp 1 — `match_format = "standard"` (Tiêu chuẩn)

```jsonc
{
  "data": {
    "match_format": "standard",
    "is_session_started": true,
    "session_status": "ongoing",
    "total_matches": 6,
    "confirmed_matches": 2,
    "current_round": null,
    // ← flat list, KHÔNG có "rounds"
    "matches": [
      {
        "id": 1,
        "name": "Trận 1",
        "mini_tournament_id": 10,
        "round_number": null,
        "club_id": 3,
        "club": { "id": 3, "name": "Badminton Club A", ... },
        "team1": {
          "id": 5,
          "name": "Đội A",
          "members": [
            { "user_id": 1, "user": { "id": 1, "full_name": "Nguyễn Văn A", "avatar_url": "...", ... } },
            { "user_id": 2, "user": { "id": 2, "full_name": "Trần Văn B", ... } }
          ],
          ...
        },
        "team2": { ... },
        "status": "completed",
        "team_win_id": 5,
        "results_by_sets": { "set1": [...], "set2": [...] },
        "competition_location": { "id": 1, "name": "Sân 1", "latitude": 10.762622, "longitude": 106.660172 },
        "has_anchor": true
      },
      {
        "id": 2,
        "name": "Trận 2",
        "mini_tournament_id": 10,
        "round_number": null,
        "club_id": 3,
        "team1": { ... },
        "team2": { ... },
        "status": "waiting_confirm",
        "team_win_id": null,
        "results_by_sets": { "set1": [...] },
        "competition_location": { ... },
        "has_anchor": false
      }
      // ... more matches
    ]
  }
}
```

> Standard: chỉ có 2 status: `waiting_confirm` | `completed`

---

### Trường hợp 2 — `match_format` = `partner_rotation` | `mixed_gender` | `rank_pairing`

```jsonc
{
  "data": {
    "match_format": "mixed_gender",
    "is_session_started": true,
    "session_status": "ongoing",
    "total_matches": 12,
    "confirmed_matches": 5,
    "current_round": 2,
    // ← KHÔNG có "matches", thay vào đó là "rounds"
    "rounds": [
      {
        "round_number": 1,
        "status": "done",
        "completed_count": 4,
        "total_count": 4,
        "matches": [
          {
            "id": 1,
            "name": "Trận 1-1",
            "mini_tournament_id": 10,
            "round_number": 1,
            "club_id": 3,
            "club": { ... },
            "team1": { ... },
            "team2": { ... },
            "status": "completed",
            "team_win_id": 5,
            "results_by_sets": { ... },
            "competition_location": { ... },
            "has_anchor": false
          }
          // ... 4 matches total
        ]
      },
      {
        "round_number": 2,
        "status": "active",
        "completed_count": 1,
        "total_count": 4,
        "matches": [
          {
            "id": 5,
            "name": "Trận 2-1",
            "mini_tournament_id": 10,
            "round_number": 2,
            "team1": { ... },
            "team2": { ... },
            "status": "going_on",
            "team_win_id": null,
            "results_by_sets": {},
            "competition_location": { ... },
            "has_anchor": false
          }
          // ... 4 matches total
        ]
      },
      {
        "round_number": 3,
        "status": "upcoming",
        "completed_count": 0,
        "total_count": 4,
        "matches": [
          {
            "id": 9,
            "name": "Trận 3-1",
            "mini_tournament_id": 10,
            "round_number": 3,
            "team1": { ... },
            "team2": { ... },
            "status": "pending",
            "team_win_id": null,
            "results_by_sets": {},
            "competition_location": { ... },
            "has_anchor": false
          }
        ]
      }
    ]
  }
}
```

> Round status: `upcoming` (chưa đấu) | `active` (đang đấu) | `done` (hoàn thành)
>
> Match status flow: `pending` → `going_on` → `waiting_confirm` → `completed`

---

## 2. MiniMatch object — chi tiết từng trường

```jsonc
{
  "id": 1,
  "name": "Trận 1-1",
  "mini_tournament_id": 10,
  "round_number": 1,
  "club_id": 3,
  "club": { ... },                    // ClubResource, null nếu club is_public = false
  "team1": {
    "id": 5,
    "name": "Đội A",
    "members": [
      {
        "user_id": 1,
        "user": {
          "id": 1,
          "full_name": "Nguyễn Văn A",
          "avatar_url": "https://...",
          ...
        }
      },
      { "user_id": 2, "user": { ... } }
    ],
    ...
  },
  "team2": { ... },
  "status": "completed",              // pending | going_on | waiting_confirm | completed
  "team_win_id": 5,                   // ID team thắng, null nếu chưa có
  "results_by_sets": {
    "set1": [
      {
        "id": 1,
        "set_number": 1,
        "team_id": 5,
        "points": 21,
        ...
      }
    ],
    "set2": [ ... ]
  },
  "competition_location": {
    "id": 1,
    "name": "Sân Badminton A",
    "latitude": 10.762622,
    "longitude": 106.660172
  },
  "has_anchor": true                  // có thành viên là anchor trong trận
}
```

---

## 3. Round object — chi tiết từng trường

```jsonc
{
  "round_number": 1,
  "status": "done",       // upcoming | active | done
  "completed_count": 4,
  "total_count": 4,
  "matches": [ ... ]       // array MiniMatch object
}
```

| Round status | Mô tả |
|---|---|
| `upcoming` | Round chưa bắt đầu |
| `active` | Round đang đấu (có ít nhất 1 match đã hoàn thành) |
| `done` | Tất cả matches trong round đã hoàn thành |

---

## 4. MiniTournament fields mới

| Field | Bảng | Kiểu | Mặc định |
|---|---|---|---|
| `match_format` | `mini_tournaments` | string?null | `null` |
| `session_status` | `mini_tournaments` | string?null | `null` |
| `is_session_started` | `mini_tournaments` | boolean | `true` (standard/partner_rotation), `false` (mixed_gender/rank_pairing) |

---

## 5. MiniMatch — 4 status

| Constant | Giá trị | Mô tả | Dùng ở format |
|---|---|---|---|
| `STATUS_PENDING` | `"pending"` | Đang chờ | 3 format có round |
| `STATUS_GOING_ON` | `"going_on"` | Đang diễn ra, chưa nhập điểm | 3 format có round |
| `STATUS_WAITING_CONFIRM` | `"waiting_confirm"` | Đã nhập điểm, chờ xác nhận | Tất cả 4 format |
| `STATUS_COMPLETED` | `"completed"` | Đã xác nhận kết quả | Tất cả 4 format |

---

## 6. Luồng `is_session_started`

```
Tạo kèo standard / partner_rotation
    → is_session_started = true (mặc định)

Tạo kèo mixed_gender / rank_pairing
    → is_session_started = false
    → Organizer gọi POST /api/mini-tournaments/{id}/start-session
    → is_session_started = true
```

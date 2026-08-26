# Match Suggestion API

## Overview

API for generating match suggestions in Mini Tournaments.

## Business Rules

- **Frontend is the source of truth** for participant list and tier (A/B)
- Only participants that are available for matching should be sent
- Participants who are "bỏ vòng", not checked-in, or not participating should NOT be sent
- Frontend can edit tier (A/B) directly on the app before generating
- Algorithm must use the exact `mini_participant_id` and `tier` from Frontend, NOT recalculate from database
- Backend merges Frontend data with database stats (played_count, consecutive, etc.)

---

## Player Pairs API

APIs for managing fixed player pairs (người ghép cặp). Players in a fixed pair will always be on the same team.

### Get All Player Pairs

```
GET /api/mini-tournaments/{miniTournamentId}/player-pairs
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "player1_id": 101,
      "player2_id": 102,
      "player1_is_guest": false,
      "player2_is_guest": false,
      "pair_color": "blue",
      "created_at": "2026-08-26T10:00:00Z"
    }
  ]
}
```

### Create Player Pair

```
POST /api/mini-tournaments/{miniTournamentId}/player-pairs
```

**Request Body:**
```json
{
  "player1_id": 101,
  "player2_id": 102,
  "player1_is_guest": false,
  "player2_is_guest": false
}
```

| Field | Type | Required | Description |
|-------|------|---------|-------------|
| `player1_id` | integer | Yes | User ID or mini_participant_id for guest |
| `player2_id` | integer | Yes | User ID or mini_participant_id for guest |
| `player1_is_guest` | boolean | No | Whether player1 is a guest (default: false) |
| `player2_is_guest` | boolean | No | Whether player2 is a guest (default: false) |

**Note:** If either player already has a pair, the old pair will be automatically removed before creating the new one.

### Delete Player Pair

```
DELETE /api/mini-tournaments/{miniTournamentId}/player-pairs/{pairId}
```
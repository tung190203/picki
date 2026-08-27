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

**Note:** All `player_id` fields always use `user_id`. Backend resolves guest status via `mini_participants` table when needed.

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
  "player2_id": 102
}
```

| Field | Type | Required | Description |
|-------|------|---------|-------------|
| `player1_id` | integer | Yes | User ID |
| `player2_id` | integer | Yes | User ID |

**Note:** If either player already has a pair, the old pair will be automatically removed before creating the new one.

### Delete Player Pair

```
DELETE /api/mini-tournaments/{miniTournamentId}/player-pairs/{pairId}
```

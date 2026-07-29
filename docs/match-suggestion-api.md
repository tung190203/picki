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

## API Endpoints

### Generate Match Suggestion

```
POST /api/match-suggestions/mini-tournaments/{miniTournamentId}/generate
```

### Regenerate Match Suggestion

```
POST /api/match-suggestions/mini-tournaments/{miniTournamentId}/regenerate
```

Regenerate automatically excludes players from the previous suggestion.

---

## Request Body

```json
{
  "mini_tournament_id": 123,
  "participants": [
    {
      "mini_participant_id": 1,
      "tier": "A"
    },
    {
      "mini_participant_id": 2,
      "tier": "B"
    },
    {
      "mini_participant_id": 3,
      "tier": "A"
    },
    {
      "mini_participant_id": 4,
      "tier": "B"
    }
  ],
  "settings": {
    "fair_play": true,
    "balance_team": true,
    "prefer_high_tier_match": true,
    "prevent_three_consecutive": true,
    "organizer_as_backup": false
  },
  "seed": 123456
}
```

### Fields

| Field | Type | Required | Description |
|-------|------|---------|-------------|
| `mini_tournament_id` | integer | Yes | Tournament ID |
| `participants` | array | Yes | List of participants for matching |
| `participants[].mini_participant_id` | integer | Yes | Participant ID from database |
| `participants[].tier` | string | Yes | Tier assigned by Frontend ("A" or "B") |
| `settings` | object | No | Matching settings (defaults to all true) |
| `settings.fair_play` | boolean | No | Equal match count for all players (default: true) |
| `settings.balance_team` | boolean | No | Balance skill levels between teams (default: true) |
| `settings.prefer_high_tier_match` | boolean | No | Prefer "căng tay" matches (default: true) |
| `settings.prevent_three_consecutive` | boolean | No | No one plays 3 consecutive matches (default: true) |
| `settings.organizer_as_backup` | boolean | No | Include organizer as backup player (default: false) |
| `seed` | integer | No | Random seed for reproducible results |

### Settings Explained

| Setting | Description |
|---------|-------------|
| `fair_play` | Ensures all players play roughly the same number of matches |
| `balance_team` | Balances skill levels (tier distribution) between Team 1 and Team 2 |
| `prefer_high_tier_match` | When possible, creates "căng tay" matches with balanced tiers |
| `prevent_three_consecutive` | Prevents any player from playing 3 matches in a row |
| `organizer_as_backup` | When enabled, organizer participants can be used as backup |

---

## Response

```json
{
  "data": {
    "match": {
      "team1": {
        "id": null,
        "name": "Team 1",
        "members": [
          {
            "id": 202,
            "user_id": 202,
            "team_id": null,
            "full_name": "Trần Đình Trưởng",
            "avatar_url": "http://localhost:8000/storage/avatars/6a4c9f7528c3a.jpg",
            "is_guest": false,
            "visibility": "open",
            "tier": "A",
            "sports": [
              {
                "sport_id": 1,
                "scores": {
                  "personal_score": "2.000",
                  "dupr_score": "0.000",
                  "vndupr_score": "1.954",
                  "trinh_score": "0.000"
                }
              }
            ]
          },
          {
            "id": 1,
            "team_id": null,
            "full_name": "Hải Nguyễn",
            "avatar_url": "https://picki.vn/storage/avatars/1766574593_694bca01aed08.jpg",
            "is_guest": false,
            "visibility": "open",
            "tier": "A",
            "sports": [
              {
                "sport_id": 1,
                "scores": {
                  "personal_score": "2.000",
                  "dupr_score": "0.000",
                  "vndupr_score": "1.993",
                  "trinh_score": "0.000"
                }
              }
            ]
          }
        ]
      },
      "team2": {
        "id": null,
        "name": "Team 2",
        "members": [
          {
            "id": 2,
            "team_id": null,
            "full_name": "Blucati",
            "avatar_url": "https://picki.vn/storage/avatars/1766653722_694cff1ac0962.jpg",
            "is_guest": false,
            "visibility": "open",
            "tier": "B",
            "sports": [
              {
                "sport_id": 1,
                "scores": {
                  "personal_score": "2.000",
                  "dupr_score": "0.000",
                  "vndupr_score": "1.295",
                  "trinh_score": "0.000"
                }
              }
            ]
          },
          {
            "id": 3,
            "team_id": null,
            "full_name": "Duy Nguyễn",
            "avatar_url": "https://picki.vn/storage/avatars/1766557707_694b880b6edfe.jpg",
            "is_guest": false,
            "visibility": "open",
            "tier": "B",
            "sports": [
              {
                "sport_id": 1,
                "scores": {
                  "personal_score": "2.000",
                  "dupr_score": "0.000",
                  "vndupr_score": "3.104",
                  "trinh_score": "0.000"
                }
              }
            ]
          }
        ]
      },
      "is_high_tier_match": true
    },
    "waiting_players": [],
    "backup_used": false,
    "backup_player": null,
    "statistics": {
      "fairness_score": 0.85,
      "balance_score": 1.0,
      "total_available_players": 4,
      "selected_count": 4,
      "waiting_count": 0
    },
    "seed": 123456,
    "rules_applied": [
      "fair_play",
      "balance_team",
      "prefer_high_tier_match"
    ],
    "messages": []
  }
}
```

### Response Fields

| Field | Description |
|-------|-------------|
| `match.team1` | Team 1 with members array |
| `match.team1.id` | Team ID (null for suggestions) |
| `match.team1.name` | Team name ("Team 1") |
| `match.team1.members` | Array of team members |
| `match.team2` | Team 2 with members array |
| `match.is_high_tier_match` | Whether this is a balanced "căng tay" match |
| `waiting_players` | Players available but not selected |
| `backup_used` | Whether a backup player was used |
| `backup_player` | Backup player details if used |
| `statistics.fairness_score` | How fair the match distribution is (0-1) |
| `statistics.balance_score` | How balanced the teams are (0-1) |
| `seed` | Random seed used for this suggestion |
| `rules_applied` | Which rules were applied in this generation |
| `messages` | Any warnings or info messages |

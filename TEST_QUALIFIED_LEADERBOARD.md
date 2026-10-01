# Test: Unified Leaderboard Qualify Logic

## Setup

```bash
php artisan migrate
# Set ranking_matches to 10 in admin UI or:
php artisan tinker --execute="App\Models\SystemSetting::where('key','ranking_matches')->update(['value' => '10']);"
php artisan cache:clear
```

## Cases

### Case A — Badge qualifies user immediately

```
ranking_matches = 10
User X: 0 trận qualified, đã cấp badge VERIFIED qua admin
```

Expected:
- `users.total_matches_has_anchor` = 0
- `matches.qualified_for_ranking` rows của X = 0
- `GET /api/home` → X xuất hiện trong `leaderboard[]`
- `GET /api/leaderboard?scope=all` → X xuất hiện

Verify SQL:
```sql
SELECT id FROM user_badges WHERE user_id = <X>; -- 1 row
SELECT COUNT(*) FROM matches m
  JOIN team_members tm ON tm.team_id IN (m.home_team_id, m.away_team_id)
  WHERE tm.user_id = <X> AND m.qualified_for_ranking = 1; -- 0
```

### Case B — Đủ qualified_matches, không badge → lên

```
ranking_matches = 10
User Y: 0 badge, đã chơi 12 trận trên các match có qualified_for_ranking = true
```

Expected:
- `GET /api/leaderboard?scope=all` → Y xuất hiện
- `GET /api/home` → Y xuất hiện trong `leaderboard[]`

### Case C — Không đủ ngưỡng + không badge → KHÔNG lên

```
ranking_matches = 10
User Z: 0 badge, 9 trận qualified
```

Expected:
- `GET /api/leaderboard?scope=all` → Z KHÔNG xuất hiện
- `GET /api/home` → Z KHÔNG xuất hiện trong `leaderboard[]`

### Case D — Trận có 1 badge user → qualified cho cả 2 đội

```
Trận M (matches): User A (VERIFIED) + User B (no badge)
Sau khi M complete → qualified_for_ranking = 1
```

Verify:
```sql
SELECT qualified_for_ranking FROM matches WHERE id = <M>; -- 1
-- Cả A và B đều được countQualifiedMatches tăng 1
```

### Case E — Trận không có badge user → qualified = false

```
Trận N (mini_matches): User C (no badge) + User D (no badge)
Sau khi N complete → qualified_for_ranking = 0
```

Verify:
```sql
SELECT qualified_for_ranking FROM mini_matches WHERE id = <N>; -- 0
```

## Negative checks

### Case F — Legacy fallback (no qualified_match set yet, but old total_matches_has_anchor high)

```
User W: không badge, không có qualified_match (cũ trước deploy),
        total_matches_has_anchor = 15
ranking_matches = 10
```

Expected:
- W vẫn xuất hiện trên BXH (fallback hoạt động).

## Sanity

```bash
php artisan route:list --path=api/leaderboard
php artisan route:list --path=api/home
```

Should compile without errors.

## Cache invalidation

Khi 1 trận complete và qualified_for_ranking vừa được set,
cache `leaderboard_qualified_user_ids:{sportId}` được xóa tự động
qua `LeaderboardQualifierService::markQualified → forgetUserIdsCache`.
TTL mặc định 60s nếu miss path.

## Manual smoke

```bash
curl -H "Authorization: Bearer <token>" "http://localhost/api/home" | jq '.data.leaderboard | length'
curl -H "Authorization: Bearer <token>" "http://localhost/api/leaderboard?scope=top50" | jq '.data.leaderboard | length'
```

Cùng 1 user được liệt kê ở cả 2 response khi user qualified.
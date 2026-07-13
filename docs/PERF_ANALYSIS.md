# Club Module — Performance Profiling Results

> **Status**: Pending — Run Phase 0 profiling before implementing any optimization.

This document records actual profiling results from Telescope, Debugbar, and DB::listen.
Update this file with real numbers before proceeding to P1.

---

## Profiling Report Template

Run the following on **staging** with production-like data volume.

### Setup

```bash
# 1. Enable Telescope on staging
# .env: TELESCOPE_ENABLED=true

# 2. Run on staging, hitting a club with 50+ members
# 3. Run on staging, hitting GET /clubs/search with 10 items
# 4. Run on staging, hitting GET /clubs/{id}/dashboard
```

---

## GET /clubs/{id} — Club Detail

**Date**: YYYY-MM-DD
**Club**: [name] (ID: X, members: N)
**Tester**: [name]

### Phase Breakdown

| Phase | Time (ms) | % of Total | Notes |
|-------|-----------|------------|-------|
| DB Queries (total) | X | X% | N queries; slowest: Y ms |
| Model Hydration | X | X% | M models hydrated |
| Business Logic (Assembler/Service) | X | X% | attachMembership, rank calc |
| Resource Serialization | X | X% | ClubResource::toArray() |
| JSON Encode | X | X% | Response size: X KB |
| **TOTAL** | **X** | **100%** | — |

### Slowest Queries

| # | SQL (truncated) | Time (ms) | Rows Scanned | Index Used |
|---|-----------------|-----------|--------------|------------|
| 1 | ... | X | Y | idx_xxx / ALL |
| 2 | ... | X | Y | idx_xxx / ALL |
| 3 | ... | X | Y | idx_xxx / ALL |

### Bottleneck Verdict

- [ ] **DB queries** (>50% of total) → Focus: Query optimization (N+1, missing index, SELECT *)
- [ ] **Model hydration** (>30% of total) → Focus: Reduce eager-loads, use projections
- [ ] **Serialization** (>20% of total) → Focus: Split resources, lazy-load heavy attributes
- [ ] **JSON encode** (>20% of total) → Focus: Reduce response size (API split, projection)

### EXPLAIN ANALYZE Results

```sql
-- Paste EXPLAIN ANALYZE output here

-- 1. isMember exists check
EXPLAIN ANALYZE
SELECT EXISTS(
  SELECT 1 FROM club_members
  WHERE club_id = ? AND user_id = ?
    AND membership_status = 'joined' AND status = 'active'
);
-- Result: ...
-- Recommendation: ...

-- 2. joinedMembers + nested eager-load
EXPLAIN ANALYZE
SELECT cm.*, u.*, us.*, s.*, uss.*
FROM club_members cm
JOIN users u ON u.id = cm.user_id
LEFT JOIN user_sport us ON us.user_id = u.id
LEFT JOIN sports s ON s.id = us.sport_id
LEFT JOIN user_sport_scores uss ON uss.user_sport_id = us.id
WHERE cm.club_id = ? AND cm.membership_status = 'joined';
-- Result: ...
-- Recommendation: ...
```

---

## GET /clubs/search — Club Search

**Date**: YYYY-MM-DD
**Filters**: [applied filters]
**Results**: N items/page

### Phase Breakdown

| Phase | Time (ms) | % of Total |
|-------|-----------|------------|
| DB Queries | X | X% |
| Hydration | X | X% |
| Serialization | X | X% |
| JSON Encode | X | X% |
| **TOTAL** | **X** | **100%** |

### EXPLAIN Results

```sql
-- Paste EXPLAIN ANALYZE for the main paginated query
```

---

## GET /clubs/{id}/dashboard — Dashboard

**Date**: YYYY-MM-DD
**Club**: [name]

### Phase Breakdown

| Phase | Time (ms) | % of Total |
|-------|-----------|------------|
| DB Queries | X | X% |
| Hydration | X | X% |
| Serialization | X | X% |
| JSON Encode | X | X% |
| **TOTAL** | **X** | **100%** |

---

## EXPLAIN ANALYZE — Hot Queries Summary

### 1. isMember exists check
```sql
EXPLAIN ANALYZE
SELECT EXISTS(
  SELECT 1 FROM club_members
  WHERE club_id = ? AND user_id = ?
    AND membership_status = 'joined' AND status = 'active'
);

-- Expected: type=ref, key=idx_club_members_user_club_status, rows=1
-- If type=ALL or rows>10: ADD index or REWRITE query
```

### 2. joinedMembers with nested eager-load
```sql
EXPLAIN ANALYZE
SELECT cm.*, u.*, us.*, s.*, uss.*
FROM club_members cm
JOIN users u ON u.id = cm.user_id
LEFT JOIN user_sport us ON us.user_id = u.id
LEFT JOIN sports s ON s.id = us.sport_id
LEFT JOIN user_sport_scores uss ON uss.user_sport_id = us.id
WHERE cm.club_id = ? AND cm.membership_status = 'joined';

-- Expected: key=club_members_club_id_membership_status_index, rows=~member_count
-- If rows>500: PAGINATE members or LIMIT
-- If type=ALL: ADD index
```

### 3. attachUnreadNotificationCount — recipient unread
```sql
EXPLAIN ANALYZE
SELECT cn.club_id, COUNT(*) as unread_count
FROM club_notifications cn
JOIN club_notification_recipients cnr ON cnr.club_notification_id = cn.id
WHERE cn.club_id IN (?)
  AND cnr.user_id = ? AND cnr.is_read = false
  AND cn.status = 'sent'
GROUP BY cn.club_id;

-- Expected: key=(club_id,status) or (user_id,is_read) depending on optimizer
-- If type=ALL: ADD (club_id,status,sent_at) index
```

### 4. attachUnreadNotificationCount — broadcast unread
```sql
EXPLAIN ANALYZE
SELECT cn.club_id, COUNT(*) AS broadcast_unread_count
FROM club_notifications cn
LEFT JOIN (
  SELECT club_id, MAX(joined_at) AS joined_at
  FROM club_members
  WHERE user_id = ? AND membership_status = 'joined' AND status = 'active'
  GROUP BY club_id
) cm ON cm.club_id = cn.club_id
WHERE cn.club_id IN (?)
  AND cn.status = 'sent'
  AND NOT EXISTS (
    SELECT 1 FROM club_notification_recipients cnr2
    WHERE cnr2.club_notification_id = cn.id
  )
  AND cn.sent_at >= COALESCE(cm.joined_at, '1970-01-01')
GROUP BY cn.club_id;

-- Expected: cn uses (club_id,status) index; cm uses (user_id,membership_status,status,club_id)
-- If slow: ADD (club_id,status,sent_at) covering index
```

### 5. nearBy scope
```sql
EXPLAIN ANALYZE
SELECT *,
  (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
   cos(radians(longitude) - radians(?)) +
   sin(radians(?)) * sin(radians(latitude)))) AS distance
FROM clubs
HAVING distance <= ? AND distance IS NOT NULL
ORDER BY distance ASC;

-- Expected: type=ALL (full table scan) is EXPECTED for geospatial
-- Mitigation: LIMIT 200 + good bounding box pre-filter
-- If rows>50000: ADD bounding box filter (minLat, maxLat, minLng, maxLng)
```

### 6. getMemberStatistics (14-way)
```sql
-- BEFORE OPTIMIZATION: Each clone runs:
EXPLAIN ANALYZE
SELECT COUNT(*) AS total FROM club_members WHERE club_id = ? AND deleted_at IS NULL;
-- Expected: key=club_id, rows=~member_count

-- AFTER OPTIMIZATION: Single query:
EXPLAIN ANALYZE
SELECT COUNT(*) as total,
  SUM(CASE WHEN status = 'active' AND membership_status = 'joined' THEN 1 ELSE 0 END) as active_members,
  ...
FROM club_members WHERE club_id = ? AND deleted_at IS NULL;
```

---

## Index Audit Summary

Run on production/staging MySQL:

```sql
-- Show all indexes on Club tables
SHOW INDEX FROM clubs;
SHOW INDEX FROM club_members;
SHOW INDEX FROM club_notifications;
SHOW INDEX FROM club_notification_recipients;
SHOW INDEX FROM club_activities;

-- Check for duplicate/redundant indexes
-- Redundant indexes found:
-- - club_activities: (club_id, status, start_time) appears 2x
-- - club_notification_recipients: (club_notification_id, user_id) duplicate of unique constraint
```

---

## Recommended Indexes (Add after P1/P2 profiling)

Based on query patterns identified in Club module code.

### club_members (most critical)

```sql
-- Supports: attachMembershipStatus, attachUnreadNotificationCount (cm subquery),
-- getMemberStatistics, getLeaderboard
CREATE INDEX idx_club_members_user_club_status
ON club_members (user_id, club_id, membership_status, status);
-- Covers: WHERE user_id=? AND membership_status=? AND status=? (attachMembershipStatus)
-- Covers: WHERE club_id=? AND membership_status=? AND status=? (getMemberStatistics)
-- Covers: WHERE user_id=? AND club_id=? (cm subquery in unread)
-- Verify with: EXPLAIN ANALYZE SELECT ... WHERE user_id=1 AND membership_status='joined' AND status='active';
```

### club_notifications

```sql
-- Supports: attachUnreadNotificationCount (recipient query)
-- Covers: WHERE club_id IN (?) AND status='sent'
CREATE INDEX idx_club_notifications_club_status
ON club_notifications (club_id, status, sent_at);
-- Covers: WHERE club_id=? AND status='sent' AND sent_at>=?
-- Verify with: EXPLAIN ANALYZE
--   SELECT cn.* FROM club_notifications cn
--   JOIN club_notification_recipients cnr ON cnr.club_notification_id = cn.id
--   WHERE cn.club_id IN (1,2,3) AND cnr.user_id=1 AND cnr.is_read=false AND cn.status='sent';
```

### club_notification_recipients

```sql
-- Supports: attachUnreadNotificationCount (recipient join)
-- Verify current index covers: (club_notification_id, user_id) or (user_id, is_read)
-- If missing: CREATE INDEX idx_cnr_user_read ON club_notification_recipients (user_id, is_read, club_notification_id);
```

### club_activities (after verify)

```sql
-- Run BEFORE adding: EXPLAIN ANALYZE the recurring series collapse query
-- If slow on recurring filter: ADD
CREATE INDEX idx_club_activities_series_status
ON club_activities (club_id, recurrence_series_id, status, start_time);
-- Verify with: EXPLAIN ANALYZE
--   SELECT COUNT(*) FROM club_activities
--   WHERE club_id=? AND recurrence_series_id IS NOT NULL
--   AND status IN ('scheduled','ongoing') AND start_time BETWEEN ? AND ?;
```

### clubs (verify existing)

```sql
-- Run BEFORE adding nearBy: EXPLAIN ANALYZE
-- See if existing (latitude, longitude) or (latitude) index exists
SHOW INDEX FROM clubs WHERE Key_name = 'idx_clubs_lat_lng';
-- If missing: CREATE INDEX idx_clubs_lat_lng ON clubs (latitude, longitude);
-- Note: scopeNearBy now uses whereBetween(lat,lng) pre-filter which can use this
```

### EXPLAIN ANALYZE Before/After Template

```sql
-- 1. attachMembershipStatus (club_members user lookup)
EXPLAIN ANALYZE
SELECT cm.*, u.full_name, u.avatar_url
FROM club_members cm
JOIN users u ON u.id = cm.user_id
WHERE cm.club_id IN (1,2,3)
  AND cm.user_id = 1;
-- Expected: key=idx_club_members_user_club_status, type=ref, rows=1

-- 2. attachUnreadNotificationCount (recipient join)
EXPLAIN ANALYZE
SELECT cn.club_id, COUNT(*) as unread_count
FROM club_notifications cn
JOIN club_notification_recipients cnr ON cnr.club_notification_id = cn.id
WHERE cn.club_id IN (1,2,3)
  AND cnr.user_id = 1
  AND cnr.is_read = false
  AND cn.status = 'sent'
GROUP BY cn.club_id;
-- Expected: key=(club_id,status) on cn; key=(user_id,is_read) on cnr

-- 3. getMemberStatistics (now single query)
EXPLAIN ANALYZE
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN membership_status = 'joined' AND status = 'active' THEN 1 ELSE 0 END) AS active_joined,
  ...
FROM club_members WHERE club_id = ? AND deleted_at IS NULL;
-- Expected: key=club_id, rows=~member_count

-- 4. getLeaderboard batch stats (new)
EXPLAIN ANALYZE
SELECT user_id, SUM(tournament_matches) ...
FROM (
  SELECT tm.user_id, COUNT(*) as tournament_matches, ... FROM matches m ...
  WHERE tm.user_id IN (1,2,3...) GROUP BY tm.user_id
  UNION ALL ...
  ...
) combined GROUP BY user_id;
-- Expected: key on team_members(user_id) or mini_team_members(user_id)
```

---

## Performance Budget Verification

After implementing optimizations, verify against budgets:

| Endpoint | Budget DB | Budget Total | Budget Response | Actual DB | Actual Total | Actual Response | Pass? |
|----------|-----------|-------------|----------------|-----------|-------------|----------------|-------|
| GET /clubs/{id} | ≤150ms | ≤250ms | ≤150KB | | | | [ ] |
| GET /clubs/{id}/members | ≤80ms | ≤120ms | ≤50KB | | | | [ ] |
| GET /clubs/{id}/statistics | ≤30ms | ≤50ms | ≤5KB | | | | [ ] |
| GET /clubs/search | ≤60ms | ≤100ms | ≤40KB | | | | [ ] |
| GET /clubs/map | ≤100ms | ≤150ms | ≤200KB | | | | [ ] |
| GET /clubs/{id}/dashboard | ≤100ms | ≤200ms | ≤100KB | | | | [ ] |

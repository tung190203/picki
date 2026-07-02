<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExplainMeQueries extends Command
{
    protected $signature = 'explain:me-queries {--user_id=1 : User ID to test with}';

    protected $description = 'Run EXPLAIN on the 6 heaviest queries from the /me endpoint';

    public function handle(): int
    {
        $userId = (int) $this->option('user_id');
        $sportId = 1;

        $this->info("Running EXPLAIN for /me queries (user_id={$userId}, sport_id={$sportId})");
        $this->line(str_repeat('=', 80));

        $queries = $this->getQueries($userId, $sportId);

        foreach ($queries as $index => $info) {
            $this->newLine();
            $this->info("Query " . ($index + 1) . ": {$info['name']}");
            $this->line(str_repeat('-', 60));

            try {
                $explain = DB::select("EXPLAIN {$info['sql']}", $info['bindings']);
                $this->table(
                    ['type', 'table', 'possible_keys', 'key', 'key_len', 'rows', 'Extra'],
                    array_map(fn($row) => [
                        $row->type ?? '',
                        $row->table ?? '',
                        $row->possible_keys ?? '',
                        $row->key ?? '',
                        $row->key_len ?? '',
                        $row->rows ?? '',
                        $row->Extra ?? '',
                    ], $explain)
                );
            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Done. Look for "type=ALL" (full table scan) and large "rows" values.');

        return Command::SUCCESS;
    }

    private function getQueries(int $userId, int $sportId): array
    {
        $userIdsCsv = (string) $userId;

        return [
            [
                'name' => 'countMatchesForBatch — tournament home team',
                'sql' => "
                    SELECT tm.user_id, COUNT(DISTINCT m.id) AS cnt
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.home_team_id
                    WHERE tm.user_id IN ({$userIdsCsv})
                      AND t.sport_id = ? AND m.status = 'completed' AND m.is_bye = 0
                    GROUP BY tm.user_id",
                'bindings' => [$sportId],
            ],
            [
                'name' => 'countMatchesForBatch — tournament away team',
                'sql' => "
                    SELECT tm.user_id, COUNT(DISTINCT m.id) AS cnt
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.away_team_id
                    WHERE tm.user_id IN ({$userIdsCsv})
                      AND t.sport_id = ? AND m.status = 'completed' AND m.is_bye = 0
                    GROUP BY tm.user_id",
                'bindings' => [$sportId],
            ],
            [
                'name' => 'countMatchesForBatch — mini team1',
                'sql' => "
                    SELECT mtm.user_id, COUNT(DISTINCT mm.id) AS cnt
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                    WHERE mtm.user_id IN ({$userIdsCsv})
                      AND mnt.sport_id = ? AND mm.status = 'completed'
                      AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
                    GROUP BY mtm.user_id",
                'bindings' => [$sportId],
            ],
            [
                'name' => 'countMatchesForBatch — quick match',
                'sql' => "
                    SELECT mh.user_id, COUNT(DISTINCT mh.quick_match_id) AS cnt
                    FROM match_histories mh
                    JOIN quick_matches qm ON mh.quick_match_id = qm.id
                    LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
                    LEFT JOIN user_sport usc ON qm.created_by = usc.user_id
                    WHERE mh.user_id IN ({$userIdsCsv})
                      AND qm.status = 'completed'
                      AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))
                    GROUP BY mh.user_id",
                'bindings' => [$sportId, $sportId],
            ],
            [
                'name' => 'getBatchSportStats — tournament home stats (UNION ALL head)',
                'sql' => "
                    SELECT tm.user_id AS user_id,
                        m.id AS match_id,
                        1 AS t_matches, 0 AS mini_matches, 0 AS qm_matches,
                        CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END AS w_t_matches,
                        0 AS w_mini_matches, 0 AS w_qm_matches
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.home_team_id
                    WHERE tm.user_id IN ({$userIdsCsv})
                      AND t.sport_id = ?
                      AND m.status = 'completed'
                      AND m.is_bye = 0",
                'bindings' => [$sportId],
            ],
            [
                'name' => 'getBatchVNRanks — all ranked scores (heavy JOIN + GROUP)',
                'sql' => "
                    SELECT u2.id, MAX(uss2.score_value) AS max_score
                    FROM users AS u2
                    JOIN user_sport AS us2 ON u2.id = us2.user_id
                    JOIN user_sport_scores AS uss2 ON us2.id = uss2.user_sport_id
                    WHERE us2.sport_id = {$sportId}
                      AND uss2.score_type = 'vndupr_score'
                      AND u2.email != 'vrplus2018@gmail.com'
                    GROUP BY u2.id",
                'bindings' => [],
            ],
        ];
    }
}

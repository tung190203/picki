<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'format',
        'num_legs',          // ✅ ĐÃ THÊM
        'match_rules',
        'rules',             // ✅ ĐÃ THÊM
        'rules_file_path',
        'format_specific_config',
    ];

    protected $casts = [
        'match_rules' => 'array',
        'format_specific_config' => 'array',
        'num_legs' => 'integer',  // ✅ ĐÃ THÊM cast
    ];

    // ============================================
    // ENUM-LIKE CONSTANTS
    // ============================================

    // Format types
    const FORMAT_MIXED = 1;
    const FORMAT_ELIMINATION = 2;
    const FORMAT_ROUND_ROBIN = 3;

    const FORMATS = [
        self::FORMAT_MIXED,
        self::FORMAT_ELIMINATION,
        self::FORMAT_ROUND_ROBIN,
    ];

    const NUM_LEGS_OPTIONS = [1, 2];

    // Winning rules
    const WIN_RULE_ONE_POINT_AWAY = 1;
    const WIN_RULE_TWO_POINT_AWAY = 2;

    // Ranking criteria
    const RANKING_WIN_DRAW_LOSE_POINTS = 1;
    const RANKING_WIN_RATE = 2;
    const RANKING_SETS_WON = 3;
    const RANKING_POINTS_WON = 4;
    const RANKING_HEAD_TO_HEAD = 5;
    const RANKING_RANDOM_DRAW = 6;

    // Seeding rules
    const SEED_LEVEL = 1;
    const SEED_SAME_CLUB_AVOID = 2;
    const SEED_RANDOM = 3;

    const SEEDING_RULES = [
        self::SEED_LEVEL => 'Điểm trình độ(Mạnh Yếu)',
        self::SEED_SAME_CLUB_AVOID => 'Cùng CLB không gặp nhau',
        self::SEED_RANDOM => 'Ngẫu nhiên',
    ];

    // ============================================
    // LABEL MAPS (để hiển thị ra UI/API)
    // ============================================

    public static function formatLabels(): array
    {
        return [
            self::FORMAT_MIXED => 'Hỗn hợp',
            self::FORMAT_ELIMINATION => 'Loại trực tiếp',
            self::FORMAT_ROUND_ROBIN => 'Vòng tròn',
        ];
    }

    public function getFormatLabelAttribute(): ?string
    {
        return self::formatLabels()[$this->format] ?? null;
    }

    public $appends = ['format_label', 'num_legs_label', 'total_teams', 'total_matches','total_matches_per_team'];

    public static function winningRuleLabels(): array
    {
        return [
            self::WIN_RULE_ONE_POINT_AWAY => 'Cách 1 điểm',
            self::WIN_RULE_TWO_POINT_AWAY => 'Cách 2 điểm',
        ];
    }

    // ============================================
    // STATIC GETTERS
    // ============================================

    public static function getFormats(): array
    {
        return array_keys(self::formatLabels());
    }

    public static function getWinningRules(): array
    {
        return array_keys(self::winningRuleLabels());
    }

    // ============================================
    // FACTORY - Tạo với default config
    // ============================================

    public static function createWithFormat(int $tournamentId, int $format, array $customConfig = []): self
    {
        if (!in_array($format, self::getFormats())) {
            throw new \InvalidArgumentException("Invalid format: {$format}");
        }

        $config = [
            'num_legs' => $customConfig['num_legs'] ?? 1,  // ✅ ĐÃ THÊM
            'match_rules' => $customConfig['match_rules'] ?? [],
            'rules' => $customConfig['rules'] ?? null,  // ✅ ĐÃ THÊM
            'rules_file_path' => $customConfig['rules_file_path'] ?? null,
            'format_specific_config' => $customConfig['format_specific_config'] ?? null,
        ];
    
        return self::create([
            'tournament_id' => $tournamentId,
            'format' => $format,
            'num_legs' => $config['num_legs'],  // ✅ ĐÃ THÊM
            'match_rules' => $config['match_rules'],
            'rules' => $config['rules'],  // ✅ ĐÃ THÊM
            'rules_file_path' => $config['rules_file_path'],
            'format_specific_config' => $config['format_specific_config'],
        ]);
    }

    public static function getDefaultConfigForFormat(int $format): array
    {
        $baseConfig = [
            'num_legs' => 1,  // ✅ ĐÃ THÊM default
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
                'winning_rule' => self::WIN_RULE_TWO_POINT_AWAY,
                'max_points' => 11,
                'serve_change_interval' => null,
            ],
        ];

        switch ($format) {
            case self::FORMAT_MIXED:
                $baseConfig['format_specific_config'] = [
                    'seeding_rules' => [self::SEED_LEVEL, self::SEED_SAME_CLUB_AVOID, self::SEED_RANDOM],
                    'ranking' => [
                        self::RANKING_WIN_DRAW_LOSE_POINTS,
                        self::RANKING_WIN_RATE,
                        self::RANKING_SETS_WON,
                        self::RANKING_POINTS_WON,
                        self::RANKING_HEAD_TO_HEAD,
                        self::RANKING_RANDOM_DRAW
                    ],
                    'pool_stage' => [
                        'name' => 'Vòng bảng',
                        'number_competing_teams' => 2,
                        'num_advancing_teams' => 2,
                    ],
                    'has_third_place_match' => false,
                    'advanced_to_next_round' => false
                ];
                break;

            case self::FORMAT_ELIMINATION:
                $baseConfig['format_specific_config'] = [
                    'seeding_rules' => [self::SEED_LEVEL, self::SEED_SAME_CLUB_AVOID, self::SEED_RANDOM],
                    'ranking' => [
                        self::RANKING_WIN_DRAW_LOSE_POINTS,
                        self::RANKING_WIN_RATE,
                        self::RANKING_SETS_WON,
                        self::RANKING_POINTS_WON,
                        self::RANKING_HEAD_TO_HEAD,
                        self::RANKING_RANDOM_DRAW
                    ],
                    'has_third_place_match' => false,
                    'advanced_to_next_round' => false
                ];
                break;

            case self::FORMAT_ROUND_ROBIN:
                $baseConfig['format_specific_config'] = [
                    'ranking' => [
                        self::RANKING_WIN_DRAW_LOSE_POINTS,
                        self::RANKING_WIN_RATE,
                        self::RANKING_SETS_WON,
                        self::RANKING_POINTS_WON,
                        self::RANKING_HEAD_TO_HEAD,
                        self::RANKING_RANDOM_DRAW
                    ],
                ];
                break;
        }

        return $baseConfig;
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(Matches::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function teamRankings()
    {
        return $this->hasMany(TeamRanking::class);
    }
    public function advancementRules()
    {
        return $this->hasMany(PoolAdvancementRule::class);
    }

    public function teams()
    {
        return $this->hasManyThrough(
            Team::class,
            Tournament::class,
            'id',
            'tournament_id',
            'tournament_id',
            'id'
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($tournamentType) {
            // Xoá matches và tất cả liên quan
            $tournamentType->matches->each(function ($match) {
                $match->delete();
            });

            // Xoá các liên quan khác
            $tournamentType->teamRankings()->delete();
            $tournamentType->advancementRules()->delete();

            if ($tournamentType->format == 1) {
                $tournamentType->groups()->delete();
            }
        });
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function formatLabel(): ?string
    {
        return self::formatLabels()[$this->format] ?? null;
    }

    public function getNumLegsLabelAttribute(): ?string
    {
        if (is_null($this->num_legs)) {
            return null;
        }
        return $this->num_legs === 1 ? 'Một lượt' : ($this->num_legs === 2 ? 'Hai lượt' : "{$this->num_legs} lượt");
    }

    public function getTotalTeamsAttribute(): ?int
    {
        return $this->tournament->max_team ?? null;
    }

    public function getTotalMatchesAttribute(): ?int
    {
        $numLegs = $this->num_legs ?? 1;
        $totalTeams = $this->tournament->max_team ?? null;
        $config = $this->format_specific_config ?? [];
    
        if (!$totalTeams || $totalTeams < 2) {
            return 0;
        }
    
        switch ($this->format) {
            case self::FORMAT_ROUND_ROBIN:
                $matches = ($totalTeams * ($totalTeams - 1)) / 2;
                return intval($matches * $numLegs);
            case self::FORMAT_ELIMINATION:
                $hasThird = filter_var($config['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $matches = max(0, $totalTeams - 1);
                if ($hasThird && $totalTeams >= 3) {
                    $matches += 1;
                }
                return intval($matches * $numLegs);
    
            case self::FORMAT_MIXED:
            default:
                $configItem = $config[0] ?? [];
                $numGroups = max(1, intval($configItem['pool_stage']['number_competing_teams'] ?? 1));
                $numAdvancing = max(0, intval($configItem['pool_stage']['num_advancing_teams'] ?? 0));
                $hasThird = filter_var($configItem['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $totalTeams = max(0, intval($totalTeams));
                $numLegs = max(1, intval($numLegs));
                $base = intdiv($totalTeams, $numGroups);
                $rem = $totalTeams % $numGroups;
                $groupMatches = 0;
                for ($i = 0; $i < $numGroups; $i++) {
                    $teamsInGroup = $base + ($i < $rem ? 1 : 0);
                    if ($teamsInGroup >= 2) {
                        $groupMatches += ($teamsInGroup * ($teamsInGroup - 1)) / 2;
                    }
                }
                $groupMatches = intval($groupMatches * $numLegs);
                $qualified = $numAdvancing * $numGroups;
                $qualified = min($qualified, $totalTeams);
                $knockoutMatches = 0;
                if ($qualified >= 2) {
                    $knockoutMatches = $qualified - 1;
                    if ($hasThird && $qualified >= 3) {
                        $knockoutMatches += 1;
                    }
                }
                return intval($groupMatches + ($knockoutMatches * $numLegs));
        }
    }

    public function getTotalMatchesPerTeamAttribute(): ?array
    {
        $numLegs = $this->num_legs ?? 1;
        $totalTeams = $this->tournament->max_team ?? null;
        $config = $this->format_specific_config ?? [];
    
        if (!$totalTeams || $totalTeams < 2) {
            return null;
        }
    
        $min = 0;
        $max = 0;
    
        switch ($this->format) {
            case self::FORMAT_ROUND_ROBIN:
                $min = ($totalTeams - 1) * $numLegs;
                $max = $min;
                break;
            case self::FORMAT_ELIMINATION:
                $hasThird = filter_var($config['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $min = 1 * $numLegs;
                $max = ceil(log($totalTeams, 2)) * $numLegs;
                if ($hasThird && $totalTeams >= 3) {
                    $max += (1 * $numLegs);
                }
                break;
    
            case self::FORMAT_MIXED:
            default:
                $configItem = $config[0] ?? [];

                $numGroups = max(1, intval($configItem['pool_stage']['number_competing_teams'] ?? 1));
                $numAdvancing = max(0, intval($configItem['pool_stage']['num_advancing_teams'] ?? 0));
                $hasThird = filter_var($configItem['has_third_place_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
                $totalTeams = max(2, intval($totalTeams));
                $numLegs = max(1, intval($numLegs));
                $base = intdiv($totalTeams, $numGroups);
                $rem = $totalTeams % $numGroups;
                $minGroupSize = $base;
                $maxGroupSize = $base + ($rem > 0 ? 1 : 0);
                $minGroupMatchesPerTeam = max(0, ($minGroupSize - 1) * $numLegs);
                $maxGroupMatchesPerTeam = max(0, ($maxGroupSize - 1) * $numLegs);
                $qualified = min($numAdvancing * $numGroups, $totalTeams);
    
                $minKO = $maxKO = 0;
                if ($qualified >= 2) {
                    $minKO = 1 * $numLegs;
                    $maxKO = ceil(log($qualified, 2)) * $numLegs;
                    if ($hasThird && $qualified >= 3) {
                        $maxKO += (1 * $numLegs);
                    }
                }
                $min = $minGroupMatchesPerTeam + $minKO;
                $max = $maxGroupMatchesPerTeam + $maxKO;
                break;
        }
    
        return [
            'min' => intval($min),
            'max' => intval($max),
        ];
    }    

    public function isMixed(): bool
    {
        return $this->format === self::FORMAT_MIXED;
    }

    public function isElimination(): bool
    {
        return $this->format === self::FORMAT_ELIMINATION;
    }

    public function isRoundRobin(): bool
    {
        return $this->format === self::FORMAT_ROUND_ROBIN;
    }

    public function getSetsPerMatch(): int
    {
        return $this->match_rules['sets_per_match'] ?? 1;
    }

    public function getWinningRuleLabel(): ?string
    {
        $rule = $this->match_rules['winning_rule'] ?? self::WIN_RULE_TWO_POINT_AWAY;
        return self::winningRuleLabels()[$rule] ?? null;
    }

    public function isLeadByTwoRule(): bool
    {
        return $this->match_rules['winning_rule'] === self::WIN_RULE_TWO_POINT_AWAY;
    }

    public function getFormatConfig(string $key, $default = null)
    {
        return data_get($this->format_specific_config, $key, $default);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeMixed($query)
    {
        return $query->where('format', self::FORMAT_MIXED);
    }

    public function scopeElimination($query)
    {
        return $query->where('format', self::FORMAT_ELIMINATION);
    }

    public function scopeRoundRobin($query)
    {
        return $query->where('format', self::FORMAT_ROUND_ROBIN);
    }

    public function scopeByTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }
}
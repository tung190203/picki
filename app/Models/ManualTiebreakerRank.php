<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lưu thứ hạng thủ công (manual tiebreaker) do BTC chỉ định khi các đội
 * bằng điểm + bằng hiệu số + đối đầu vòng tròn cycle (H2H không phân định).
 *
 * Mỗi row đại diện cho "BTC gán team X = rank Y trong cụm đồng hạng của group Z
 * (hoặc candidate_type=null/runner_up/third_place)".
 *
 * - GroupStandingRanker::applyManualTiebreakers() query theo
 *   (tournament_type_id, group_id) để sắp xếp lại các cụm đồng hạng nội bộ.
 * - CrossGroupComparisonService query theo (tournament_type_id, group_id, candidate_type)
 *   để sắp xếp các cụm candidate Nhì/Ba khác bảng.
 */
class ManualTiebreakerRank extends Model
{
    protected $table = 'manual_tiebreaker_ranks';

    protected $fillable = [
        'tournament_type_id',
        'group_id',
        'team_id',
        'manual_rank',
        'candidate_type',
        'set_by_user_id',
        'set_at',
    ];

    protected $casts = [
        'manual_rank' => 'integer',
        'set_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function setByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'set_by_user_id');
    }
}

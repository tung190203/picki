<?php

namespace App\Events;

use App\Models\Matches;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đẩy tỉ số mọi trận của một giải lên kênh public `tournament.{id}`.
 *
 * Màn picker đứng ở giải A thì chỉ subscribe kênh này — không phải nghe từng trận,
 * và không nhận nhiễu từ giải khác.
 */
class TournamentScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $tournamentId, public Matches $match)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('tournament.' . $this->tournamentId)];
    }

    public function broadcastAs(): string
    {
        return 'tournament.score_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournamentId,
            'trigger_match_id' => $this->match->id,
            'version' => (int) $this->match->match_version,
            'matches' => self::snapshot($this->tournamentId),
        ];
    }

    /**
     * Snapshot các trận đang diễn ra của giải.
     *
     * is_going_on = đã bắt đầu (started_at khác null) VÀ chưa confirm kết quả.
     * Trận chưa bắt đầu / đã confirm sẽ không vào danh sách — picker chỉ cần
     * những trận có thể đổi tỉ số trong tương lai gần.
     */
    public static function snapshot(int $tournamentId): array
    {
        return static::buildQuery($tournamentId)
            ->with('results')
            ->get()
            ->map(function (Matches $m) {
                $currentSet = (int) ($m->current_set ?: 1);
                // Phải lấy dòng của ĐỘI NHÀ: `team_score` là điểm của chính đội đó,
                // lấy nhầm dòng đội khách sẽ đảo ngược tỉ số team1/team2.
                $result = $m->results->firstWhere(
                    fn ($r) => (int) $r->set_number === $currentSet
                        && (int) $r->team_id === (int) $m->home_team_id
                );

                return [
                    'match_id' => $m->id,
                    'current_set' => $currentSet,
                    'live_status' => $m->live_status,
                    'team1_score' => (int) ($result?->team_score ?? 0),
                    'team2_score' => (int) ($result?->opponent_score ?? 0),
                    'version' => (int) $m->match_version,
                    'is_going_on' => static::isGoingOn($m),
                    // `status`/`is_finished` để FE không phải gọi lại API sau khi confirm.
                    'status' => $m->status,
                    'is_finished' => $m->status === Matches::STATUS_COMPLETED
                        || $m->live_status === Matches::LIVE_STATUS_FINISHED,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Truy vấn các trận thuộc giải (qua tournament_types.tournament_id).
     */
    public static function buildQuery(int $tournamentId): Builder
    {
        return Matches::query()
            ->whereHas('tournamentType', fn (Builder $q) => $q->where('tournament_id', $tournamentId))
            ->where('is_bye', 0)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id');
    }

    public static function isGoingOn(Matches $match): bool
    {
        if (!$match->started_at) {
            return false;
        }
        if ($match->status === 'completed') {
            return false;
        }
        // Trận đã kết thúc (trọng tài bấm "finish") -> không còn "đang đá".
        if ($match->live_status === Matches::LIVE_STATUS_FINISHED) {
            return false;
        }
        // Hai đội đã xác nhận => kết quả chốt, không còn "đang đá".
        return !($match->home_team_confirm && $match->away_team_confirm);
    }
}
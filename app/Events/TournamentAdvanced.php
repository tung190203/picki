<?php

namespace App\Events;

use App\Http\Controllers\LivescorePickerController;
use App\Models\Matches;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đẩy đội vừa được điền vào các trận vòng sau.
 *
 * Tách khỏi `TournamentScoreUpdated` vì cái đó là snapshot tỉ số (bắn liên tục
 * mỗi lần nhập điểm), còn cái này chỉ bắn 1 lần khi trận chốt xong — payload
 * nhỏ, không cần FE refetch lại cả giải để thấy tên đội vòng sau.
 */
class TournamentAdvanced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $tournamentId, public Matches $triggerMatch)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('tournament.' . $this->tournamentId)];
    }

    public function broadcastAs(): string
    {
        return 'tournament.advanced';
    }

    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournamentId,
            'trigger_match_id' => $this->triggerMatch->id,
            'matches' => self::nextRounds($this->tournamentId, (int) $this->triggerMatch->round),
        ];
    }

    /**
     /**
     * Các trận vòng sau đã có ít nhất MỘT đội (trận bán kết mới có 1 đội).
     *
     * Quét theo `round` thay vì lần theo `next_match_id` vì pool không gắn
     * `next_match_id` — nó đi qua `PoolAdvancementRule`. Một truy vấn là đủ,
     * và số trận vòng sau luôn nhỏ hơn tổng số trận của giải.
     *
     * Chỉ gửi trận `round > roundTrậnVừaXong`: trận cùng/before vừa được bước
     * trước ghi đội thắng vào (tỉ số đã đi qua `tournament.score_updated`).
     *
     * Shape `team1`/`team2` cố ý giống hệt `LivescorePickerController::matches()`
     * để FE patch thay vì replace không mất field.
     */
    public static function nextRounds(int $tournamentId, int $afterRound): array
    {
        return Matches::query()
            ->whereHas('tournamentType', fn (Builder $q) => $q->where('tournament_id', $tournamentId))
            ->where('is_bye', 0)
            ->where('round', '>', $afterRound)
            ->whereNotNull('home_team_id')
            ->with([
                'homeTeam:id,name',
                'awayTeam:id,name',
                // Members để màn picker hiện tên/avatar người chơi ngay, khỏi chờ
                // gọi lại /livescore/tournaments/{id}/matches.
                'homeTeam.members',
                'homeTeam.guestMembers.participant',
                'awayTeam.members',
                'awayTeam.guestMembers.participant',
            ])
            ->get()
            ->map(fn (Matches $m) => [
                'match_id' => (int) $m->id,
                'round' => (int) $m->round,
                'team1' => LivescorePickerController::formatPickerTeam(
                    $m->home_team_id,
                    $m->homeTeam
                ),
                // Trận bán kết mới có 1 đội -> `team2` = shape TBD của API.
                'team2' => LivescorePickerController::formatPickerTeam(
                    $m->away_team_id,
                    $m->awayTeam
                ),
            ])
            ->values()
            ->toArray();
    }
}
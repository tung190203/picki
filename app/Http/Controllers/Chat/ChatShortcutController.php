<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatShortcutController extends Controller
{
    /**
     * Danh sách Kèo đấu gần đây của user để chọn gửi thẻ.
     */
    public function matches(Request $request)
    {
        $userId = Auth::id();
        $q = $request->query('q');

        // Chỉ hiển thị kèo đấu công khai (is_private = false) hoặc kèo riêng tư mà chính user đã tham gia/tạo
        $query = MiniTournament::query()
            ->where(function ($sub) use ($userId) {
                $sub->where('is_private', false)
                    ->orWhere(function ($joined) use ($userId) {
                        $joined->where('created_by', $userId)
                            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
                    });
            })
            ->latest('id')
            ->limit(20);

        if ($q) {
            $query->where('name', 'like', "%{$q}%");
        }

        $matches = $query->get(['id', 'name', 'poster', 'start_time', 'end_time', 'max_players']);

        $data = $matches->map(fn ($m) => [
            'id' => $m->id,
            'title' => $m->name,
            'poster_url' => $m->poster,
            'start_time' => optional($m->start_time)->toDateTimeString(),
            'end_time' => optional($m->end_time)->toDateTimeString(),
            'card_type' => 'card_mini_tournament',
        ]);

        return ResponseHelper::success($data, 'Lấy danh sách kèo thành công');
    }

    /**
     * Danh sách Giải đấu gần đây để chọn gửi thẻ.
     */
    public function tournaments(Request $request)
    {
        $userId = Auth::id();
        $q = $request->query('q');

        // Chỉ hiển thị giải đấu công khai (is_private = false) hoặc giải riêng tư mà chính user đã tham gia/tạo
        $query = Tournament::query()
            ->where(function ($sub) use ($userId) {
                $sub->where('is_private', false)
                    ->orWhere(function ($joined) use ($userId) {
                        $joined->where('created_by', $userId)
                            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
                    });
            })
            ->latest('id')
            ->limit(20);

        if ($q) {
            $query->where('name', 'like', "%{$q}%");
        }

        $tournaments = $query->get(['id', 'name', 'poster', 'start_date', 'end_date', 'status']);

        $data = $tournaments->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->name,
            'poster_url' => $t->poster,
            'start_date' => optional($t->start_date)->toDateString(),
            'status' => $t->status,
            'card_type' => 'card_tournament',
        ]);

        return ResponseHelper::success($data, 'Lấy danh sách giải đấu thành công');
    }

    /**
     * Danh sách Người chơi có liên quan gần với user (hay chơi cùng kèo/giải, cùng CLB, ở gần) để chọn gửi thẻ hoặc bắt đầu chat.
     */
    public function players(Request $request, \App\Services\User\UserSuggestService $suggestService)
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $userId = (int) $authUser->id;
        $q = trim((string) $request->query('q', ''));

        $lat = $request->query('lat') !== null
            ? (float) $request->query('lat')
            : ($authUser->latitude !== null ? (float) $authUser->latitude : null);
        $lng = $request->query('lng') !== null
            ? (float) $request->query('lng')
            : ($authUser->longitude !== null ? (float) $authUser->longitude : null);

        // 1. Tận dụng UserSuggestService để lấy các user có quan hệ gần (played_tournament -> same_club -> nearby)
        $groups = $suggestService->suggest($userId, $lat, $lng);

        $relatedUsers = $groups['played_tournament']
            ->merge($groups['same_club'])
            ->merge($groups['nearby'])
            ->filter(function (User $u) {
                if ($u->is_banned || $u->is_guest || $u->visibility === 'private') {
                    return false;
                }
                if ($u->visibility === 'friend-only') {
                    $rel = $u->preloaded_relation;
                    $hasRelation = $rel && (
                        ($rel->matches_together ?? 0) > 0 ||
                        ($rel->mini_tournaments_together ?? 0) > 0 ||
                        ($rel->tournaments_together ?? 0) > 0 ||
                        ($rel->clubs_together ?? 0) > 0
                    );
                    return $hasRelation || in_array($u->category ?? '', ['played_tournament', 'same_club'], true);
                }
                return true;
            })
            ->values();

        if ($q !== '') {
            $relatedUsers = $relatedUsers
                ->filter(fn (User $u) => mb_stripos((string) $u->full_name, $q) !== false)
                ->values();
        }

        $users = $relatedUsers->take(20);

        // 2. Nếu có từ khóa tìm kiếm q mà danh sách người quen chưa đủ 20 kết quả -> tìm thêm user hợp lệ theo tên
        if ($q !== '' && $users->count() < 20) {
            $excludeIds = $users->pluck('id')->push($userId)->unique()->all();
            $remaining = 20 - $users->count();

            $extraUsers = User::query()
                ->with(['sports.sport', 'sports.scores', 'clubs'])
                ->whereNotIn('id', $excludeIds)
                ->where('is_banned', false)
                ->where('is_guest', false)
                ->where('visibility', '!=', 'private')
                ->where('full_name', 'like', "%{$q}%")
                ->where(function ($sub) use ($userId) {
                    $sub->where('visibility', 'open')
                        ->orWhere(function ($f) use ($userId) {
                            $f->where('visibility', 'friend-only')
                                ->where(function ($rel) use ($userId) {
                                    $rel->whereHas('clubs', function ($cq) use ($userId) {
                                        $cq->whereIn('clubs.id', function ($subClub) use ($userId) {
                                            $subClub->select('club_id')
                                                ->from('club_members')
                                                ->where('user_id', $userId)
                                                ->where('membership_status', 'joined')
                                                ->where('status', 'active');
                                        });
                                    })
                                    ->orWhereHas('participants', function ($tp) use ($userId) {
                                        $tp->whereIn('tournament_id', function ($tq) use ($userId) {
                                            $tq->select('tournament_id')
                                                ->from('participants')
                                                ->where('user_id', $userId);
                                        });
                                    })
                                    ->orWhereHas('miniParticipants', function ($mp) use ($userId) {
                                        $mp->whereIn('mini_tournament_id', function ($mq) use ($userId) {
                                            $mq->select('mini_tournament_id')
                                                ->from('mini_participants')
                                                ->where('user_id', $userId);
                                        });
                                    });
                                });
                        });
                })
                ->limit($remaining)
                ->get();

            if ($extraUsers->isNotEmpty()) {
                $suggestService->attachPlayedTogetherRelations($userId, $extraUsers);
                $users = $users->concat($extraUsers)->values();
            }
        }

        $data = $users->map(function (User $u) {
            $scores = $u->relationLoaded('sports')
                ? ($u->sports->firstWhere('sport_id', 1)?->scores ?? collect())
                : collect();
            $vnduprScore = $scores->firstWhere('score_type', 'vndupr_score');
            $rating = $vnduprScore?->score_value !== null
                ? round((float) $vnduprScore->score_value, 2)
                : ($u->self_score !== null ? (float) $u->self_score : null);

            $rel = $u->preloaded_relation ?? null;

            return [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'avatar_url' => $u->avatar_url,
                'rating' => $rating,
                'category' => $u->category ?? null,
                'category_text' => $u->category_text ?? null,
                'relation' => $rel ? [
                    'matches_together' => (int) ($rel->matches_together ?? 0),
                    'mini_tournaments_together' => (int) ($rel->mini_tournaments_together ?? 0),
                    'tournaments_together' => (int) ($rel->tournaments_together ?? 0),
                    'clubs_together' => (int) ($rel->clubs_together ?? 0),
                ] : null,
                'card_type' => 'card_player',
            ];
        })->values();

        return ResponseHelper::success($data, 'Lấy danh sách người chơi thành công');
    }

    /**
     * Danh sách CLB của user để chọn gửi thẻ.
     */
    public function clubs(Request $request)
    {
        $userId = Auth::id();
        $q = $request->query('q');

        $query = Club::query()
            ->whereHas('members', fn ($m) => $m->where('user_id', $userId)->where('membership_status', 'joined')->where('status', 'active'))
            ->limit(20);

        if ($q) {
            $query->where('name', 'like', "%{$q}%");
        }

        $clubs = $query->get(['id', 'name', 'logo_url']);

        $data = $clubs->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'avatar_url' => $c->logo_url,
            'card_type' => 'card_club',
        ]);

        return ResponseHelper::success($data, 'Lấy danh sách CLB thành công');
    }
}

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
     * Danh sách Người chơi (bạn bè, thành viên cùng CLB, tìm kiếm) để chọn gửi thẻ.
     */
    public function players(Request $request)
    {
        $userId = Auth::id();
        $q = $request->query('q');

        // Loại trừ user bị khóa, user ảo, và user để chế độ riêng tư (visibility = private)
        // Nếu user để friend-only: chỉ cho phép nếu đã từng cùng CLB hoặc cùng giải/kèo
        $query = User::query()
            ->where('id', '!=', $userId)
            ->where('is_banned', false)
            ->where('is_guest', false)
            ->where('visibility', '!=', 'private')
            ->where(function ($sub) use ($userId) {
                $sub->where('visibility', 'open')
                    ->orWhere(function ($f) use ($userId) {
                        $f->where('visibility', 'friend-only')
                            ->where(function ($rel) use ($userId) {
                                // Cùng CLB
                                $rel->whereHas('clubs', function ($cq) use ($userId) {
                                    $cq->whereIn('clubs.id', function ($subClub) use ($userId) {
                                        $subClub->select('club_id')
                                            ->from('club_members')
                                            ->where('user_id', $userId)
                                            ->where('membership_status', 'joined')
                                            ->where('status', 'active');
                                    });
                                })
                                // Hoặc cùng tham gia giải đấu
                                ->orWhereHas('participants', function ($tp) use ($userId) {
                                    $tp->whereIn('tournament_id', function ($tq) use ($userId) {
                                        $tq->select('tournament_id')
                                            ->from('participants')
                                            ->where('user_id', $userId);
                                    });
                                })
                                // Hoặc cùng tham gia kèo đấu
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
            ->limit(20);

        if ($q) {
            $query->where('full_name', 'like', "%{$q}%");
        } else {
            // Mặc định lấy user mới nhất
            $query->latest('id');
        }

        $users = $query->get(['id', 'full_name', 'avatar_url', 'self_score']);

        $data = $users->map(fn ($u) => [
            'id' => $u->id,
            'full_name' => $u->full_name,
            'avatar_url' => $u->avatar_url,
            'rating' => $u->self_score ?? null,
            'card_type' => 'card_player',
        ]);

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

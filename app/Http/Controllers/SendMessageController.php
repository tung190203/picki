<?php

namespace App\Http\Controllers;

use App\Events\MiniTournamentMessageSent;
use App\Events\TournamentMessageSent;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MessageResource;
use App\Models\MiniTournament;
use App\Models\MiniTournamentMessage;
use App\Models\Tournament;
use App\Models\TournamentMessage;
use App\Models\User;
use App\Notifications\MiniTournamentMessageNotification;
use App\Notifications\TournamentMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SendMessageController extends Controller
{
    public function storeMessageMiniTour(Request $request, $tournamentId)
    {
        $request->validate([
            'type' => 'required|in:text,image,voice,emoji,file',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        $tournament = MiniTournament::findOrFail($tournamentId);

        // Lấy danh sách user thực sự thuộc tournament (user trực tiếp + team members)
        $allUserIds = $tournament->all_users->pluck('id');

        // Kiểm tra quyền gửi message
        if (!$allUserIds->contains(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền gửi tin nhắn vào giải đấu này', 403);
        }

        $content = $request->input('content', null);
        $meta = null;

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('chat', 'public');
            $content = $path;
            $meta = [
                'filename' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
            ];
        }

        $message = MiniTournamentMessage::create([
            'mini_tournament_id' => $tournamentId,
            'user_id' => Auth::id(),
            'type' => $request->type,
            'content' => $content,
            'meta' => $meta,
        ])->load('user');
        // Gửi thông báo đến tất cả user trong tournament, trừ người gửi
        foreach ($allUserIds as $userId) {
            if ($userId != Auth::id()) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new MiniTournamentMessageNotification($message));
                }
            }
        }
        broadcast(new MiniTournamentMessageSent(new MessageResource($message->load('user'))))->toOthers();

        return ResponseHelper::success(new MessageResource($message->load('user')), 'Gửi tin nhắn thành công');
    }

    public function getMessagesMiniTour(Request $request, $tournamentId)
    {
        $validate = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:200'
        ]);
        $tournament = MiniTournament::withFullRelations()->findOrFail($tournamentId);
        $allStaffIds = $tournament->staff->pluck('id');
        $allUserIds = $tournament->all_users->pluck('id');

        $allIds = array_merge($allStaffIds->toArray(), $allUserIds->toArray());

        // Kiểm tra quyền xem message
        if (!in_array(Auth::id(), $allIds)) {
            return ResponseHelper::error('Bạn không có quyền xem tin nhắn trong giải đấu này', 403);
        }

        $messages = MiniTournamentMessage::where('mini_tournament_id', $tournamentId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->paginate($validate['per_page'] ?? MiniTournamentMessage::PER_PAGE);

        $meta = [
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
        ];

        return ResponseHelper::success(MessageResource::collection($messages), 'Lấy tin nhắn thành công', 200, $meta);
    }

    public function storeMessageTour(Request $request, $tournamentId)
    {
        $request->validate([
            'type' => 'required|in:text,image,voice,emoji,file',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);
        $tournament = Tournament::findOrFail($tournamentId);

        $allUserIds = $tournament->all_users->pluck('id');

        if (!$allUserIds->contains(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền gửi tin nhắn vào giải đấu này', 403);
        }
        $content = $request->input('content', null);
        $meta = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('chat', 'public');
            $content = $path;
            $meta = [
                'filename' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
            ];
        }
        $message = TournamentMessage::create([
            'tournament_id' => $tournamentId,
            'user_id' => Auth::id(),
            'type' => $request->type,
            'content' => $content,
            'meta' => $meta,
        ])->load('user');

        foreach ($allUserIds as $userId) {
            if ($userId != Auth::id()) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new TournamentMessageNotification($message));
                }
            }
        }

        broadcast(new TournamentMessageSent(new MessageResource($message->load('user'))))->toOthers();

        return ResponseHelper::success(new MessageResource($message->load('user')), 'Gửi tin nhắn thành công');
    }

    public function getMessagesTour(Request $request, $tournamentId)
    {
        $validate = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:200'
        ]);
        $tournament = Tournament::withFullRelations()->findOrFail($tournamentId);
        $allStaffIds = $tournament->staff->pluck('id');
        $allUserIds = $tournament->all_users->pluck('id');

        $allIds = array_merge($allStaffIds->toArray(), $allUserIds->toArray());

        if (!in_array(Auth::id(), $allIds)) {
            return ResponseHelper::error('Bạn không có quyền xem tin nhắn trong giải đấu này', 403);
        }

        $messages = TournamentMessage::where('tournament_id', $tournamentId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($validate['per_page'] ?? TournamentMessage::PER_PAGE);

        $meta = [
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
        ];

        return ResponseHelper::success(
            MessageResource::collection($messages->reverse()->values()),
            'Lấy tin nhắn thành công',
            200,
            $meta
        );
    }
}

<?php

use App\Models\Matches;
use App\Models\MiniTournament;
use App\Models\QuickMatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mini-tournament.{tournamentId}', function ($user, $tournamentId) {
    $tournament = MiniTournament::find($tournamentId);
    if (!$tournament) return false;

    $hasAccess = $tournament?->all_users->pluck('id')->contains($user->id);

    return $hasAccess;
});

Broadcast::channel('tournament.{tournamentId}', function ($user, $tournamentId) {
    $tournament = Tournament::find($tournamentId);
    if (!$tournament) {
        return false;
    }

    $hasAccess = $tournament->all_users->pluck('id')->contains($user->id);

    return $hasAccess;
});

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('quick-match.{id}', function ($user, $id) {
    $quickMatch = Cache::remember(
        "quick_match_channel:{$id}",
        now()->addSeconds(30),
        fn () => QuickMatch::find($id)
    );

    if (!$quickMatch) {
        return false;
    }

    $isCreator = (int) $user->id === (int) $quickMatch->created_by;
    $allPlayerIds = array_merge($quickMatch->team_a ?? [], $quickMatch->team_b ?? []);
    $isPlayer = in_array($user->id, $allPlayerIds);

    return $isCreator || $isPlayer;
});

/*
|--------------------------------------------------------------------------
| Super Admin Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('super_admin', function ($user) {
    return $user && $user->is_super_admin;
});

Broadcast::channel('DashboardAdminChannel', function ($user) {
    return $user && $user->is_super_admin;
});

/*
|--------------------------------------------------------------------------
| User Presence Channel (Global Online Status)
|--------------------------------------------------------------------------
*/

Broadcast::channel('user.presence', function ($user) {
    if (!$user) {
        return false;
    }

    return [
        'id' => $user->id,
        'full_name' => $user->full_name,
        'avatar_url' => $user->avatar_url,
    ];
});

/*
|--------------------------------------------------------------------------
| Match Score Channel
|--------------------------------------------------------------------------
*/

Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    $match = Matches::find($matchId);
    if (!$match) return false;

    // Cho phép referee, super_admin, hoặc team members
    if ($user->is_super_admin) return true;
    if ((int) $match->referee_id === (int) $user->id) return true;

    $userId = $user->id;
    $isHomeTeam = $match->homeTeam && $match->homeTeam->members->contains('user_id', $userId);
    $isAwayTeam = $match->awayTeam && $match->awayTeam->members->contains('user_id', $userId);

    return $isHomeTeam || $isAwayTeam;
});
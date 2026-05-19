<?php

use App\Models\MiniTournament;
use App\Models\QuickMatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\Broadcast;

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
    $quickMatch = QuickMatch::find($id);
    if (!$quickMatch) return false;

    if ((int) $user->id === (int) $quickMatch->created_by) {
        return true;
    }

    $allPlayerIds = array_merge($quickMatch->team_a ?? [], $quickMatch->team_b ?? []);
    return in_array($user->id, $allPlayerIds);
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
    \Log::info('[Broadcast Channel] DashboardAdminChannel authorization check', [
        'user_id' => $user?->id,
        'is_super_admin' => $user?->is_super_admin,
    ]);
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
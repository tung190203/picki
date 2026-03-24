<?php

namespace App\Http\Controllers;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaPreviewController extends Controller
{
    public function home(Request $request): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $title = config('app.name') . ' - Nền tảng Pickleball Việt Nam';
        $description = 'PICKI - Ứng dụng kết nối cộng đồng Pickleball. Tìm CLB, tham gia giải đấu, quản lý hoạt động và kết nối với người chơi.';
        $image = $this->absoluteUrl(asset('favicon.png'));
        $url = $this->canonicalUrl($request, '/');

        return view('meta.home', compact('title', 'description', 'image', 'url'));
    }

    public function club(Request $request, int $id): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $club = Club::with('profile')->find($id);

        if (!$club) {
            return view('app');
        }

        $title = $club->name;
        $description = $club->profile?->description
            ? \Str::limit(strip_tags($club->profile->description), 160)
            : "CLB {$club->name} trên PICKI";
        $image = $this->absoluteUrl($club->profile?->cover_image_url ?? $club->logo_url ?? asset('favicon.png'));
        $url = $this->canonicalUrl($request, "/clubs/{$id}");

        return view('meta.club', compact('title', 'description', 'image', 'url'));
    }

    public function tournament(Request $request, int $id): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $tournament = Tournament::find($id);

        if (!$tournament) {
            return view('app');
        }

        $title = $tournament->name;
        $description = $tournament->description
            ? \Str::limit(strip_tags($tournament->description), 160)
            : "Giải đấu {$tournament->name} trên PICKI";
        $image = $this->absoluteUrl($tournament->poster_url ?? asset('favicon.png'));
        $url = $this->canonicalUrl($request, "/tournament-detail/{$id}");

        return view('meta.tournament', compact('title', 'description', 'image', 'url'));
    }

    public function miniTournament(Request $request, int $id): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $miniTournament = MiniTournament::find($id);

        if (!$miniTournament) {
            return view('app');
        }

        $title = $miniTournament->name;
        $description = $miniTournament->description
            ? \Str::limit(strip_tags($miniTournament->description), 160)
            : "Giải đấu {$miniTournament->name} trên PICKI";
        $image = $miniTournament->poster
            ? $this->absoluteUrl(asset('storage/' . $miniTournament->poster))
            : $this->absoluteUrl(asset('favicon.png'));
        $url = $this->canonicalUrl($request, "/mini-tournament-detail/{$id}");

        return view('meta.mini-tournament', compact('title', 'description', 'image', 'url'));
    }

    public function clubActivity(Request $request, int $clubId, int $activityId): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $activity = ClubActivity::with('club')->find($activityId);

        if (!$activity || (int) $activity->club_id !== $clubId) {
            return view('app');
        }

        $club = $activity->club;
        $title = $activity->title . ' - ' . ($club?->name ?? 'CLB');
        $description = $activity->description
            ? \Str::limit(strip_tags($activity->description), 160)
            : "Hoạt động {$activity->title} tại {$club?->name} trên PICKI";
        $imageUrl = $activity->qr_code_url
            ? (str_starts_with($activity->qr_code_url, 'http') ? $activity->qr_code_url : asset('storage/' . $activity->qr_code_url))
            : ($club?->logo_url ?? asset('favicon.png'));
        $image = $this->absoluteUrl($imageUrl);
        $url = $this->canonicalUrl($request, "/clubs/{$clubId}/activities/{$activityId}");

        return view('meta.club-activity', compact('title', 'description', 'image', 'url'));
    }

    public function miniMatch(Request $request, int $id): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $match = MiniMatch::withFullRelations()->find($id);

        if (!$match) {
            return view('app');
        }

        $tournament = $match->miniTournament;
        $team1 = $match->team1;
        $team2 = $match->team2;

        $title = $match->name ?: "Kèo {$team1->name} vs {$team2->name}";
        $description = "Kèo Pickleball: {$team1->name} vs {$team2->name}";
        $description .= $tournament ? " - {$tournament->name}" : '';
        $description = \Str::limit($description, 160);

        $image = $tournament?->poster
            ? $this->absoluteUrl(asset('storage/' . $tournament->poster))
            : $this->absoluteUrl(asset('favicon.png'));

        $url = $this->canonicalUrl($request, "/mini-match/{$id}/verify");

        return view('meta.mini-match', compact('title', 'description', 'image', 'url'));
    }

    protected function canonicalUrl(Request $request, string $path): string
    {
        $baseUrl = rtrim(config('app.frontend_url') ?: config('app.url'), '/');

        return $baseUrl . $path;
    }

    protected function absoluteUrl(?string $url): string
    {
        if (empty($url)) {
            return url(asset('favicon.png'));
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }
}

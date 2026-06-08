<?php

namespace App\Http\Controllers;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaPreviewController extends Controller
{
    public function home(Request $request): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $title = config('app.name');
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

        $title = config('app.name');
        $description = $club->profile?->description
            ? \Str::limit(strip_tags($club->profile->description), 160)
            : "CLB {$club->name} trên PICKI";
        $imageUrl = $club->profile?->cover_image_url ?: $club->logo_url ?: null;
        $image = $this->absoluteUrl($imageUrl);
        $url = $this->canonicalUrl($request, "/clubs/{$id}");

        return view('meta.club', compact('title', 'description', 'image', 'url'));
    }

    public function profile(Request $request, int $id): View
    {
        if (!$request->attributes->get('is_crawler', false)) {
            return view('app');
        }

        $user = User::with(['sports.sport', 'clubs'])->find($id);

        if (!$user) {
            return view('app');
        }

        $sportNames = $user->sports
            ?->pluck('sport.name')
            ->filter()
            ->join(', ') ?: 'Pickleball';

        $title = config('app.name');
        $description = "Hồ sơ Pickleball của {$user->full_name} trên PICKI. "
            . "Tham gia cộng đồng với {$sportNames}.";
        $image = $this->absoluteUrl($user->avatar_url);
        $url = $this->canonicalUrl($request, "/profile/{$id}");

        return view('meta.profile', compact('title', 'description', 'image', 'url'));
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

        $title = config('app.name');
        $description = $tournament->description
            ? \Str::limit(strip_tags($tournament->description), 160)
            : "Giải đấu {$tournament->name} trên PICKI";
        $posterUrl = $tournament->poster_url ?: null;
        $image = $this->absoluteUrl($posterUrl);
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

        $title = config('app.name');
        $description = $miniTournament->description
            ? \Str::limit(strip_tags($miniTournament->description), 160)
            : "Kèo đấu {$miniTournament->name} trên PICKI";
        $posterUrl = $miniTournament->poster;
        if ($posterUrl) {
            $image = str_starts_with($posterUrl, 'http')
                ? $this->absoluteUrl($posterUrl)
                : $this->absoluteUrl(asset('storage/' . $posterUrl));
        } else {
            $image = $this->absoluteUrl(asset('favicon.png'));
        }
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
        $title = config('app.name');
        $description = $activity->description
            ? \Str::limit(strip_tags($activity->description), 160)
            : "Hoạt động {$activity->title} tại {$club?->name} trên PICKI";
        $qrCodeUrl = $activity->qr_code_url;
        $imageUrl = null;
        if ($qrCodeUrl) {
            $imageUrl = str_starts_with($qrCodeUrl, 'http') ? $qrCodeUrl : asset('storage/' . $qrCodeUrl);
        } elseif ($club?->logo_url) {
            $imageUrl = $club->logo_url;
        }
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

        $title = config('app.name');
        $description = "Kèo Pickleball: {$team1->name} vs {$team2->name}";
        $description .= $tournament ? " - {$tournament->name}" : '';
        $description = \Str::limit($description, 160);

        $posterUrl = $tournament?->poster;
        if ($posterUrl) {
            $image = str_starts_with($posterUrl, 'http')
                ? $this->absoluteUrl($posterUrl)
                : $this->absoluteUrl(asset('storage/' . $posterUrl));
        } else {
            $image = $this->absoluteUrl(asset('favicon.png'));
        }

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

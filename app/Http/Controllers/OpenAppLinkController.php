<?php

namespace App\Http\Controllers;

use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenAppLinkController extends Controller
{
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
        $posterUrl = $tournament->poster_url ?: null;
        $image = $this->absoluteUrl($posterUrl);
        $url = $this->canonicalUrl($request, "/l/tournament/{$id}");

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
            : "Kèo đấu {$miniTournament->name} trên PICKI";
        $posterUrl = $miniTournament->poster;
        if ($posterUrl) {
            $image = str_starts_with($posterUrl, 'http')
                ? $this->absoluteUrl($posterUrl)
                : $this->absoluteUrl(asset('storage/' . $posterUrl));
        } else {
            $image = $this->absoluteUrl(asset('favicon.png'));
        }
        $url = $this->canonicalUrl($request, "/l/mini_tournament/{$id}");

        return view('meta.mini-tournament', compact('title', 'description', 'image', 'url'));
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
        $imageUrl = $club->profile?->cover_image_url ?: $club->logo_url ?: null;
        $image = $this->absoluteUrl($imageUrl);
        $url = $this->canonicalUrl($request, "/l/club/{$id}");

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

        $title = $user->full_name . ' - PICKI';
        $description = "Hồ sơ Pickleball của {$user->full_name} trên PICKI. "
            . "Tham gia cộng đồng với {$sportNames}.";
        $image = $this->absoluteUrl($user->avatar_url);
        $url = $this->canonicalUrl($request, "/l/profile/{$id}");

        return view('meta.profile', compact('title', 'description', 'image', 'url'));
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

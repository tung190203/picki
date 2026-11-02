<?php

namespace App\Providers;

use App\Models\Club\Club;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\Participant;
use App\Models\QuickMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Observers\ClubObserver;
use App\Observers\MatchCacheObserver;
use App\Observers\MiniParticipantObserver;
use App\Observers\MiniTournamentObserver;
use App\Observers\ParticipantObserver;
use App\Observers\TournamentObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\UserSportMatchCounter::class);
        $this->app->singleton(\App\Services\Club\ClubDetailAssembler::class);
        $this->app->singleton(\App\Services\Geocoding\GeocodingManager::class);
        $this->app->singleton(\App\Services\Geocoding\GoongKeyResolver::class);
        // Singleton để cache kết quả check dùng chung cho resource + controller
        // trong cùng 1 request (tránh query lặp).
        $this->app->singleton(\App\Services\EventEditGuardService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Club::observe(ClubObserver::class);
        Matches::observe(MatchCacheObserver::class);
        MiniMatch::observe(MatchCacheObserver::class);
        MiniParticipant::observe(MiniParticipantObserver::class);
        MiniTournament::observe(MiniTournamentObserver::class);
        Participant::observe(ParticipantObserver::class);
        QuickMatch::observe(MatchCacheObserver::class);
        Tournament::observe(TournamentObserver::class);
        User::observe(UserObserver::class);

        $this->configureRateLimiting();
        $this->registerSlowQueryListener();
    }

    protected function registerSlowQueryListener(): void
    {
        if (! in_array(config('app.env'), ['local', 'development', 'dev', 'staging'], true)) {
            return;
        }

        DB::listen(function ($query) {
            $time = ($query->time ?? 0) / 1000; // convert ms to seconds
            if ($time > 0.1) { // >100ms
                $context = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => round(($query->time ?? 0), 2),
                    'connection' => $query->connectionName ?? 'default',
                ];

                // Add request context if available
                if (app()->runningInConsole() === false) {
                    $context['url'] = request()->fullUrl();
                    $context['method'] = request()->method();
                    $context['user_id'] = auth()->id();
                    if (request()->route()) {
                        $context['route'] = request()->route()->uri();
                    }
                }

                Log::channel('slow_queries')->warning('Slow DB query (>100ms)', $context);
            }
        });
    }

    protected function configureRateLimiting(): void
    {
        $isLocalOrDev = in_array(config('app.env'), ['local', 'development', 'dev', 'staging'], true);

        RateLimiter::for('api', function (Request $request) use ($isLocalOrDev) {
            if ($isLocalOrDev) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('clubs', function (Request $request) use ($isLocalOrDev) {
            if ($isLocalOrDev) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}

<?php

namespace App\Providers;

use App\Models\Club\Club;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\QuickMatch;
use App\Models\User;
use App\Observers\ClubObserver;
use App\Observers\MatchCacheObserver;
use App\Observers\MiniTournamentObserver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Club::observe(ClubObserver::class);
        Matches::observe(MatchCacheObserver::class);
        MiniMatch::observe(MatchCacheObserver::class);
        MiniTournament::observe(MiniTournamentObserver::class);
        QuickMatch::observe(MatchCacheObserver::class);
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
            if ($query->time > 100) {
                Log::channel('slow_queries')->warning('Slow query (>100ms)', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => round($query->time, 2),
                ]);
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

<?php

namespace App\Providers;

use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Observers\ClubObserver;
use App\Observers\MiniTournamentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Club::observe(ClubObserver::class);
        MiniTournament::observe(MiniTournamentObserver::class);

        $this->configureRateLimiting();
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

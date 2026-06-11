<?php

namespace App\Providers;

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
        // Per-user summary creation limit (cost guardrail) — 429 when exceeded.
        RateLimiter::for('summaries', function (Request $request) {
            $perHour = (int) config('services.summaries.rate_limit_per_hour', 20);

            return Limit::perHour($perHour)->by((string) $request->user()?->id);
        });
    }
}

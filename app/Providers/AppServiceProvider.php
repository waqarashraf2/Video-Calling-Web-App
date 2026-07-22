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
        foreach (config('videochat.rate_limits') as $name => $setting) {
            RateLimiter::for('videochat-'.$name, function (Request $request) use ($setting) {
                [$max, $minutes] = array_map('intval', explode(',', $setting));

                return Limit::perMinutes(max(1, $minutes), max(1, $max))->by($request->session()->getId().'|'.$request->ip());
            });
        }
    }
}

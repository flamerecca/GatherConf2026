<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('member-login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('member-invite', fn (Request $request) => Limit::perHour(30)
            ->by('member-invite|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-guest', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip().'|'.preg_replace('/\.en$/', '', (string) $request->route()?->getName())));
    }
}

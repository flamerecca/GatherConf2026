<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::createAssetPathsUsing(self::assetUrl(...));

        RateLimiter::for('member-login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('member-invite', fn (Request $request) => Limit::perHour(30)
            ->by('member-invite|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-message', fn (Request $request) => Limit::perMinute(20)
            ->by('member-message|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-password', fn (Request $request) => Limit::perMinute(5)
            ->by('member-password|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-task', fn (Request $request) => Limit::perMinute(20)
            ->by('member-task|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('results', fn (Request $request) => Limit::perMinute(30)->by('results|'.$request->ip()));

        RateLimiter::for('member-bottle', fn (Request $request) => Limit::perMinute(10)
            ->by('member-bottle|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-community-message', fn (Request $request) => Limit::perMinute(5)
            ->by('member-community-message|'.($request->user('member')?->getKey() ?? $request->ip())));

        RateLimiter::for('member-guest', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip().'|'.preg_replace('/\.en$/', '', (string) $request->route()?->getName())));
    }

    /**
     * Point built Vite files at the cached asset route instead of the static build directory.
     */
    public static function assetUrl(string $path, ?bool $secure = null): string
    {
        $prefix = 'build/assets/';

        return url(str_starts_with($path, $prefix) ? 'assets/'.substr($path, strlen($prefix)) : $path, [], $secure);
    }
}

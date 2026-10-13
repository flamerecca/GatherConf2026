<?php

use App\Http\Controllers\Member\CenterController;
use App\Http\Controllers\Member\EmailVerificationController;
use App\Http\Controllers\Member\LoginController;
use App\Http\Controllers\Member\LoginLinkController;
use App\Http\Controllers\Member\PasswordResetController;
use App\Http\Controllers\Member\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    app()->setLocale('zh_TW');

    return view('landing');
})->name('landing');

Route::get('/en', function () {
    app()->setLocale('en');

    return view('landing');
})->name('landing.en');

Route::get('/code-of-conduct', function () {
    app()->setLocale('zh_TW');

    return view('code-of-conduct');
})->name('conduct');

Route::get('/en/code-of-conduct', function () {
    app()->setLocale('en');

    return view('code-of-conduct');
})->name('conduct.en');

Route::get('/communities', function () {
    app()->setLocale('zh_TW');

    return view('communities');
})->name('communities');

Route::get('/en/communities', function () {
    app()->setLocale('en');

    return view('communities');
})->name('communities.en');

foreach (['' => 'zh_TW', 'en' => 'en'] as $prefix => $locale) {
    $s = $locale === 'en' ? '.en' : '';

    Route::prefix($prefix)->middleware(['member.locale:'.$locale, 'member.active'])->group(function () use ($s): void {
        Route::middleware('guest:member')->group(function () use ($s): void {
            Route::get('/register', [RegisterController::class, 'create'])->name('member.register'.$s);
            Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:member-guest')->name('member.register.store'.$s);
            Route::get('/login', [LoginController::class, 'create'])->name('member.login'.$s);
            Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:member-login')->name('member.login.store'.$s);
            Route::post('/login/link', [LoginLinkController::class, 'request'])->middleware('throttle:member-guest')->name('member.login.link.request'.$s);
            Route::get('/login/link/{token}', [LoginLinkController::class, 'consume'])->middleware('throttle:20,1')->name('member.login.link.consume'.$s);
            Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('member.password.request'.$s);
            Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:member-guest')->name('member.password.email'.$s);
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('member.password.reset'.$s);
            Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:member-guest')->name('member.password.update'.$s);
        });

        Route::middleware('auth:member')->group(function () use ($s): void {
            Route::post('/logout', [LoginController::class, 'destroy'])->name('member.logout'.$s);
            Route::get('/member', [CenterController::class, 'show'])->name('member.center'.$s);
            Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('member.verification.notice'.$s);
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('member.verification.send'.$s);
        });

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('member.verification.verify'.$s);
    });
}

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow: /admin\n\nSitemap: ".route('sitemap')."\n")
        ->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $urlset = view('sitemap', ['pages' => ['landing', 'conduct', 'communities']])->render();

    return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$urlset)
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

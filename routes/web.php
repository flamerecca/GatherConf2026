<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Member\BlockController;
use App\Http\Controllers\Member\BottleController;
use App\Http\Controllers\Member\CarrotTaskController;
use App\Http\Controllers\Member\CenterController;
use App\Http\Controllers\Member\CommunityMessageController;
use App\Http\Controllers\Member\EmailVerificationController;
use App\Http\Controllers\Member\FriendController;
use App\Http\Controllers\Member\FriendInvitationController;
use App\Http\Controllers\Member\GoogleLoginController;
use App\Http\Controllers\Member\LoginController;
use App\Http\Controllers\Member\LoginLinkController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\PasswordController;
use App\Http\Controllers\Member\PasswordResetController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\QuizController;
use App\Http\Controllers\Member\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/{file}', AssetController::class)
    ->where('file', '[A-Za-z0-9._-]+')
    ->withoutMiddleware('web')
    ->name('assets');

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

Route::get('/agenda', function () {
    app()->setLocale('zh_TW');

    return view('agenda');
})->name('agenda');

Route::get('/en/agenda', function () {
    app()->setLocale('en');

    return view('agenda');
})->name('agenda.en');

Route::get('/communities', function () {
    app()->setLocale('zh_TW');

    return view('communities');
})->name('communities');

Route::get('/en/communities', function () {
    app()->setLocale('en');

    return view('communities');
})->name('communities.en');

Route::get('/call-for-sponsor', function () {
    app()->setLocale('zh_TW');

    return view('call-for-sponsor');
})->name('call-for-sponsor');

Route::get('/en/call-for-sponsor', function () {
    app()->setLocale('en');

    return view('call-for-sponsor');
})->name('call-for-sponsor.en');

Route::get('/login/google/callback', [GoogleLoginController::class, 'callback'])->middleware(['guest:member', 'throttle:member-guest'])->name('member.google.callback');

foreach (['' => 'zh_TW', 'en' => 'en'] as $prefix => $locale) {
    $s = $locale === 'en' ? '.en' : '';

    Route::prefix($prefix)->middleware(['member.locale:'.$locale, 'member.active'])->group(function () use ($s): void {
        Route::middleware('guest:member')->group(function () use ($s): void {
            Route::get('/register', [RegisterController::class, 'create'])->name('member.register'.$s);
            Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:member-guest')->name('member.register.store'.$s);
            Route::get('/register/sent', [RegisterController::class, 'sent'])->name('member.register.sent'.$s);
            Route::get('/login', [LoginController::class, 'create'])->name('member.login'.$s);
            Route::get('/login/google', [GoogleLoginController::class, 'redirect'])->middleware('throttle:member-guest')->name('member.google.redirect'.$s);
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

        Route::middleware(['auth:member', 'member.verified'])->group(function () use ($s): void {
            Route::get('/friends', [FriendController::class, 'index'])->name('member.friends'.$s);
            Route::post('/friends/code', [FriendController::class, 'regenerateCode'])->name('member.friends.code.regenerate'.$s);
            Route::get('/friends/add/{code}', [FriendInvitationController::class, 'show'])->name('member.friends.add'.$s);
            Route::post('/friends/add/{code}', [FriendInvitationController::class, 'store'])->middleware('throttle:member-invite')->name('member.friends.invite'.$s);
            Route::post('/friends/{friendship}/accept', [FriendController::class, 'accept'])->name('member.friends.accept'.$s);
            Route::delete('/friends/{friendship}', [FriendController::class, 'destroy'])->name('member.friends.decline'.$s);
            Route::post('/friends/{member}/block', [BlockController::class, 'store'])->name('member.friends.block'.$s);
            Route::delete('/friends/{member}/block', [BlockController::class, 'destroy'])->name('member.friends.unblock'.$s);
            Route::get('/member/profile', [ProfileController::class, 'edit'])->name('member.profile.edit'.$s);
            Route::put('/member/profile', [ProfileController::class, 'update'])->middleware('throttle:member-password')->name('member.profile.update'.$s);
            Route::get('/member/password', [PasswordController::class, 'edit'])->name('member.password.edit'.$s);
            Route::put('/member/password', [PasswordController::class, 'update'])->middleware('throttle:member-password')->name('member.password.change'.$s);
            Route::get('/messages/{friendship}', [MessageController::class, 'show'])->name('member.messages.show'.$s);
            Route::post('/messages/{friendship}', [MessageController::class, 'store'])->middleware('throttle:member-message')->name('member.messages.store'.$s);
            Route::get('/member/tasks', [CarrotTaskController::class, 'index'])->name('member.tasks'.$s);
            Route::get('/member/tasks/{code}', [CarrotTaskController::class, 'complete'])->middleware('throttle:member-task')->name('member.tasks.complete'.$s);
            Route::get('/member/bottles', [BottleController::class, 'index'])->name('member.bottles'.$s);
            Route::get('/member/bottles/new', [BottleController::class, 'create'])->name('member.bottles.create'.$s);
            Route::post('/member/bottles', [BottleController::class, 'store'])->middleware('throttle:member-bottle')->name('member.bottles.store'.$s);
            Route::post('/member/bottles/pick', [BottleController::class, 'pick'])->middleware('throttle:member-bottle')->name('member.bottles.pick'.$s);
            Route::get('/member/bottles/{bottle}', [BottleController::class, 'show'])->name('member.bottles.show'.$s);
            Route::post('/member/bottles/{bottle}/reply', [BottleController::class, 'reply'])->middleware('throttle:member-bottle')->name('member.bottles.reply'.$s);
            Route::post('/member/bottles/{bottle}/report', [BottleController::class, 'report'])->middleware('throttle:member-bottle')->name('member.bottles.report'.$s);
            Route::get('/member/communities/{slug}/message', [CommunityMessageController::class, 'create'])->name('member.community-message.create'.$s);
            Route::post('/member/communities/{slug}/message', [CommunityMessageController::class, 'store'])->middleware('throttle:member-community-message')->name('member.community-message.store'.$s);
            Route::get('/member/community-messages', [CommunityMessageController::class, 'index'])->name('member.community-messages'.$s);
        });

        Route::get('/bbti', [QuizController::class, 'intro'])->name('bbti'.$s);
        Route::get('/quiz', [QuizController::class, 'show'])->name('quiz'.$s);
        Route::post('/quiz', [QuizController::class, 'store'])->middleware('throttle:quiz-store')->name('quiz.store'.$s);
        Route::get('/results/{attempt:uuid}', [QuizController::class, 'result'])->middleware('throttle:results')->name('quiz.result'.$s);

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('member.verification.verify'.$s);
    });
}

Route::get('/results/{attempt:uuid}/bunny', [QuizController::class, 'bunny'])->middleware('throttle:results')->name('quiz.result.bunny');

Route::get('/robots.txt', function () {
    $disallow = collect(['/admin', '/member', '/login', '/register', '/forgot-password', '/reset-password', '/email/verify', '/quiz', '/en/quiz', '/en/member', '/en/login', '/en/register', '/en/forgot-password', '/en/reset-password', '/en/email/verify'])
        ->map(fn (string $path) => 'Disallow: '.$path)
        ->implode("\n");

    return response("User-agent: *\n".$disallow."\n\nSitemap: ".route('sitemap')."\n")
        ->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/llms.txt', function () {
    $communities = collect(config('communities'))
        ->map(fn (array $c) => '- ['.$c['name']['en'].']('.$c['url'].'): '.$c['description']['en'])
        ->implode("\n");

    $sponsors = collect(config('sponsors'))
        ->map(fn (array $s) => '- ['.$s['name']['en'].']('.$s['url'].'), '.$s['tier'])
        ->implode("\n");

    $url = collect(['landing', 'landing.en', 'agenda', 'conduct', 'communities', 'call-for-sponsor', 'bbti'])
        ->mapWithKeys(fn (string $name) => [$name => route($name)]);

    $body = <<<MD
# GatherConf 2026

> GatherConf 2026 is a one-day conference that brings Taiwan's tech communities into the same room. Date: 2026-11-21. Venue: National Taiwan University, General Teaching Building, rooms 201-203. About 120 attendees, two tracks with eight sessions, plus open space. Pages are available in Traditional Chinese and English, English URLs start with /en.

## Pages

- [Home]({$url['landing']}): overview, schedule, partner communities and sponsors
- [Agenda]({$url['agenda']}): two tracks, eight sessions
- [Code of conduct]({$url['conduct']}): behaviour expected of everyone at the event
- [Communities]({$url['communities']}): partner communities
- [Call for sponsors]({$url['call-for-sponsor']}): sponsorship tiers and how to join
- [BBTI quiz]({$url['bbti']}): a playful 12-question maker personality quiz with 16 bunny types

## Partner communities

{$communities}

## Sponsors

{$sponsors}

## Optional

- [Traditional Chinese home]({$url['landing']}): the default language
- [English home]({$url['landing.en']}): English version
MD;

    return response($body."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms');

Route::get('/humans.txt', function () {
    $communities = collect(config('communities'))->map(fn (array $c) => $c['name']['en'])->implode(', ');
    $sponsors = collect(config('sponsors'))->map(fn (array $s) => $s['name']['en'])->implode(', ');

    $site = url('/');

    $body = <<<TXT
/* TEAM */
Developer: Recca Chao
Site: {$site}

/* THANKS */
Partner communities: {$communities}
Sponsors: {$sponsors}

/* SITE */
Event: GatherConf 2026, 2026-11-21, National Taiwan University
Standards: HTML5, CSS3, WCAG 2.2 AA
Software: Laravel, Filament, Tailwind CSS, Vite
Hosting: Laravel Cloud
TXT;

    return response($body."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('humans');

Route::get('/sitemap.xml', function () {
    $urlset = view('sitemap', ['pages' => ['landing', 'conduct', 'agenda', 'bbti', 'communities', 'call-for-sponsor']])->render();

    return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$urlset)
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

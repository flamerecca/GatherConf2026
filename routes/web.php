<?php

use App\Http\Controllers\Member\BlockController;
use App\Http\Controllers\Member\BottleController;
use App\Http\Controllers\Member\CarrotTaskController;
use App\Http\Controllers\Member\CenterController;
use App\Http\Controllers\Member\CommunityMessageController;
use App\Http\Controllers\Member\EmailVerificationController;
use App\Http\Controllers\Member\FriendController;
use App\Http\Controllers\Member\FriendInvitationController;
use App\Http\Controllers\Member\LoginController;
use App\Http\Controllers\Member\LoginLinkController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\PasswordController;
use App\Http\Controllers\Member\PasswordResetController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\QuizController;
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

foreach (['' => 'zh_TW', 'en' => 'en'] as $prefix => $locale) {
    $s = $locale === 'en' ? '.en' : '';

    Route::prefix($prefix)->middleware(['member.locale:'.$locale, 'member.active'])->group(function () use ($s): void {
        Route::middleware('guest:member')->group(function () use ($s): void {
            Route::get('/register', [RegisterController::class, 'create'])->name('member.register'.$s);
            Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:member-guest')->name('member.register.store'.$s);
            Route::get('/register/sent', [RegisterController::class, 'sent'])->name('member.register.sent'.$s);
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
            Route::get('/quiz', [QuizController::class, 'show'])->name('quiz'.$s);
            Route::post('/quiz', [QuizController::class, 'store'])->middleware('throttle:member-task')->name('quiz.store'.$s);
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
        Route::get('/results/{attempt:uuid}', [QuizController::class, 'result'])->middleware('throttle:results')->name('quiz.result'.$s);

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('member.verification.verify'.$s);
    });
}

Route::get('/robots.txt', function () {
    $disallow = collect(['/admin', '/member', '/login', '/register', '/forgot-password', '/reset-password', '/email/verify', '/quiz', '/en/quiz', '/en/member', '/en/login', '/en/register', '/en/forgot-password', '/en/reset-password', '/en/email/verify'])
        ->map(fn (string $path) => 'Disallow: '.$path)
        ->implode("\n");

    return response("User-agent: *\n".$disallow."\n\nSitemap: ".route('sitemap')."\n")
        ->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $urlset = view('sitemap', ['pages' => ['landing', 'conduct', 'agenda', 'bbti', 'communities', 'call-for-sponsor']])->render();

    return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$urlset)
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

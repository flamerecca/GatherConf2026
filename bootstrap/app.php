<?php

use App\Http\Middleware\EnsureMemberEmailIsVerified;
use App\Http\Middleware\EnsureMemberIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'member.locale' => SetLocale::class,
            'member.active' => EnsureMemberIsActive::class,
            'member.verified' => EnsureMemberEmailIsVerified::class,
        ]);

        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureMemberEmailIsVerified::class);
        $middleware->prependToPriorityList(before: EnsureMemberEmailIsVerified::class, prepend: EnsureMemberIsActive::class);

        $middleware->redirectGuestsTo(
            fn (Request $request) => route($request->is('en/*') ? 'member.login.en' : 'member.login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request) => route($request->is('en/*') ? 'member.center.en' : 'member.center'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

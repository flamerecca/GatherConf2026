<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Throwable;

abstract class MemberController extends Controller
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function localizedRoute(string $name, array $parameters = []): string
    {
        return route($name.(app()->getLocale() === 'en' ? '.en' : ''), $parameters);
    }

    protected function guard(): StatefulGuard
    {
        return Auth::guard('member');
    }

    /**
     * Run a mail-sending step without letting a transport failure break the request.
     */
    protected function sendQuietly(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

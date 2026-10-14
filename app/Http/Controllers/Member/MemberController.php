<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

    protected function takeLinkQuota(string $email): void
    {
        $key = MemberLoginLink::throttleKey($email);

        abort_if(RateLimiter::tooManyAttempts($key, MemberLoginLink::HOURLY_LIMIT), 429);

        RateLimiter::hit($key, 3600);
    }

    protected function sendLoginLink(Member $member): void
    {
        $token = MemberLoginLink::issueFor($member);

        $this->sendQuietly(fn () => $member->notify(
            (new MemberLoginLinkNotification($this->localizedRoute('member.login.link.consume', ['token' => $token])))
                ->locale(app()->getLocale()),
        ));
    }
}

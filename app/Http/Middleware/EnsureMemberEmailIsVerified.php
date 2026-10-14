<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member !== null && ! $member->hasVerifiedEmail()) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect(route($request->is('en/*') ? 'member.verification.notice.en' : 'member.verification.notice'));
        }

        return $next($request);
    }
}

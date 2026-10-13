<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member !== null && ! $member->is_active) {
            Auth::guard('member')->logout();
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            return redirect(route($request->is('en/*') ? 'member.login.en' : 'member.login'))
                ->withErrors(['email' => __('member.errors.inactive')]);
        }

        return $next($request);
    }
}

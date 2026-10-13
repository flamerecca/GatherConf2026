<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

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
}

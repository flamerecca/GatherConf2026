<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends MemberController
{
    public function create(): View
    {
        return view('member.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $member = Member::query()->where('email', Str::lower(trim($credentials['email'])))->first();

        if ($member === null || ! $member->hasPassword() || ! Hash::check($credentials['password'], $member->password)) {
            throw ValidationException::withMessages(['email' => __('member.errors.failed')]);
        }

        if (! $member->is_active) {
            throw ValidationException::withMessages(['email' => __('member.errors.inactive')]);
        }

        $this->guard()->login($member, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended($this->localizedRoute('member.center'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->guard()->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect(route(app()->getLocale() === 'en' ? 'landing.en' : 'landing'));
    }
}

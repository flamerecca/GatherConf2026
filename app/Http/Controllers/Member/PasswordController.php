<?php

namespace App\Http\Controllers\Member;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends MemberController
{
    private const LINK_LOGIN_WINDOW_SECONDS = 900;

    public function edit(Request $request): View
    {
        return view('member.password', [
            'hasPassword' => $this->guard()->user()->hasPassword(),
            'needsCurrent' => $this->needsCurrentPassword($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $this->guard()->user();
        $needsCurrent = $this->needsCurrentPassword($request);

        $rules = ['password' => ['required', 'string', 'confirmed', Password::min(8)]];

        if ($needsCurrent) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if ($needsCurrent && ! Hash::check($data['current_password'], $member->password)) {
            throw ValidationException::withMessages(['current_password' => __('member.password.current_wrong')]);
        }

        $member->forceFill(['password' => $data['password']])->setRememberToken(Str::random(60));
        $member->save();

        $request->session()->regenerate();

        return redirect($this->localizedRoute('member.password.edit'))->with('status', __('member.password.saved'));
    }

    private function needsCurrentPassword(Request $request): bool
    {
        if (! $this->guard()->user()->hasPassword()) {
            return false;
        }

        $linkLogin = $request->session()->get('member_link_login');

        return ! is_array($linkLogin)
            || ($linkLogin['member_id'] ?? null) !== $this->guard()->user()->getKey()
            || ! is_int($linkLogin['at'] ?? null)
            || now()->timestamp - $linkLogin['at'] > self::LINK_LOGIN_WINDOW_SECONDS;
    }
}

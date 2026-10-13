<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends MemberController
{
    public function create(): View
    {
        return view('member.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $this->sendQuietly(fn () => Password::broker('members')->sendResetLink(['email' => Str::lower(trim($data['email']))]));

        return back()->with('status', __('member.forgot.sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('member.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('members')->reset(
            [
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (Member $member, string $password): void {
                $member->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $member->save();

                event(new PasswordReset($member));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('member.reset.invalid')]);
        }

        return redirect($this->localizedRoute('member.login'))->with('status', __('member.reset.done'));
    }
}

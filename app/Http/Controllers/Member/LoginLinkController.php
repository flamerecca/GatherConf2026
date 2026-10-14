<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginLinkController extends MemberController
{
    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        $email = Str::lower(trim($data['email']));

        $this->takeLinkQuota($email);

        $member = Member::query()->where('email', $email)->where('is_active', true)->first();

        if ($member !== null) {
            $this->sendLoginLink($member);
        }

        return back()->with('link_status', __('member.login.link_sent'));
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $member = MemberLoginLink::consume($token);

        if ($member === null) {
            return redirect($this->localizedRoute('member.login'))
                ->withErrors(['link' => __('member.errors.link_invalid')]);
        }

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        $this->guard()->login($member);
        $request->session()->regenerate();
        $request->session()->put('member_link_login_at', now()->timestamp);

        return redirect()->intended($this->localizedRoute('member.center'));
    }
}

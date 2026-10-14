<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends MemberController
{
    public function notice(): View|RedirectResponse
    {
        if ($this->guard()->user()->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.center'));
        }

        return view('member.verify-notice');
    }

    public function send(Request $request): RedirectResponse
    {
        $member = $this->guard()->user();

        if ($member->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.center'));
        }

        $member->sendEmailVerificationNotification();

        return back()->with('status', __('member.verify.resent'));
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $member = Member::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($member->getEmailForVerification()), $hash), 403);

        if (! $member->hasVerifiedEmail()) {
            $member->discardCredentials();
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        $destination = $this->guard()->check() ? 'member.center' : 'member.login';

        return redirect($this->localizedRoute($destination))->with('status', __('member.verify.done'));
    }
}

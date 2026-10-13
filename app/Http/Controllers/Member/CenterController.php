<?php

namespace App\Http\Controllers\Member;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CenterController extends MemberController
{
    public function show(): View|RedirectResponse
    {
        $member = $this->guard()->user();

        if (! $member->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.verification.notice'));
        }

        return view('member.center', ['member' => $member]);
    }
}

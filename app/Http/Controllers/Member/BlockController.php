<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Http\RedirectResponse;

class BlockController extends MemberController
{
    public function store(Member $member): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($me->canBlock($member), 404);

        MemberBlock::block($me, $member);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.blocked'));
    }

    public function destroy(Member $member): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($me->hasBlocked($member), 404);

        MemberBlock::unblock($me, $member);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.unblocked'));
    }
}

<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
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

        $blockedIds = $member->blockedPairMemberIds();

        return view('member.center', [
            'member' => $member,
            'pendingInvitations' => Friendship::query()
                ->pending()
                ->involving($member)
                ->where('requester_id', '!=', $member->getKey())
                ->with(['low', 'high'])
                ->get()
                ->filter(fn (Friendship $friendship) => $friendship->otherMember($member)->is_active)
                ->reject(fn (Friendship $friendship) => $blockedIds->contains($friendship->otherMember($member)->getKey()))
                ->count(),
        ]);
    }
}

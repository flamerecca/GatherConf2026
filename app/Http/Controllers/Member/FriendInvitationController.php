<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FriendInvitationController extends MemberController
{
    public function show(string $code): View
    {
        $me = $this->guard()->user();
        $owner = $this->ownerFor($code, $me);

        $relation = 'none';

        if (! $owner->is($me)) {
            $friendship = Friendship::between($me, $owner);

            $relation = match (true) {
                $friendship === null => 'none',
                $friendship->isAccepted() => 'friends',
                $friendship->requester_id === $me->getKey() => 'sent',
                default => 'received',
            };
        }

        return view('member.friend-add', [
            'owner' => $owner,
            'isSelf' => $owner->is($me),
            'relation' => $relation,
            'code' => $code,
        ]);
    }

    public function store(string $code): RedirectResponse
    {
        $me = $this->guard()->user();
        $owner = $this->ownerFor($code, $me);

        if ($owner->is($me)) {
            return redirect($this->localizedRoute('member.friends'));
        }

        $result = Friendship::invite($me, $owner);

        abort_if($result === Friendship::INVITE_BLOCKED, 404);

        return redirect($this->localizedRoute('member.friends'))
            ->with('status', __('member.friends.results.'.$result));
    }

    private function ownerFor(string $code, Member $me): Member
    {
        $owner = Member::query()
            ->where('friend_code', $code)
            ->where('is_active', true)
            ->first();

        abort_if($owner === null, 404);
        abort_if(! $owner->is($me) && ($me->hasBlocked($owner) || $me->isBlockedBy($owner)), 404);

        return $owner;
    }
}

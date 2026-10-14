<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\MemberBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FriendController extends MemberController
{
    public function index(): View
    {
        $me = $this->guard()->user();

        $friendships = Friendship::query()
            ->involving($me)
            ->with(['low', 'high'])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Friendship $friendship) => $friendship->otherMember($me)->is_active);

        $blocked = MemberBlock::query()
            ->where('blocker_id', $me->getKey())
            ->with('blocked')
            ->latest('created_at')
            ->get();

        return view('member.friends', [
            'member' => $me,
            'friendUrl' => $me->friendUrl(),
            'friends' => $friendships->filter(fn (Friendship $friendship) => $friendship->isAccepted())->values(),
            'received' => $friendships
                ->reject(fn (Friendship $friendship) => $friendship->isAccepted())
                ->filter(fn (Friendship $friendship) => $friendship->requester_id !== $me->getKey())
                ->values(),
            'sent' => $friendships
                ->reject(fn (Friendship $friendship) => $friendship->isAccepted())
                ->filter(fn (Friendship $friendship) => $friendship->requester_id === $me->getKey())
                ->values(),
            'blocked' => $blocked,
        ]);
    }

    public function regenerateCode(): RedirectResponse
    {
        $this->guard()->user()->regenerateFriendCode();

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.regenerated'));
    }

    public function accept(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->acceptBy($this->guard()->user()), 404);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.accepted'));
    }

    public function destroy(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->removeBy($this->guard()->user()), 404);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.removed'));
    }
}

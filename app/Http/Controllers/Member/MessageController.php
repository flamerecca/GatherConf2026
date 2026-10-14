<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class MessageController extends MemberController
{
    public function show(Request $request, Friendship $friendship): View
    {
        [$me, $friend] = $this->conversationFor($friendship);

        Message::markRead($me, $friend);

        $beforeId = $request->integer('before') ?: null;
        $messages = Message::latestPage($me, $friend, $beforeId);

        $hasOlder = $messages->isNotEmpty()
            && Message::query()->between($me, $friend)->where('id', '<', $messages->first()->id)->exists();

        return view('member.messages', [
            'me' => $me,
            'friend' => $friend,
            'friendship' => $friendship,
            'messages' => $messages,
            'olderBeforeId' => $hasOlder ? $messages->first()->id : null,
        ]);
    }

    public function store(Request $request, Friendship $friendship): RedirectResponse
    {
        [$me, $friend] = $this->conversationFor($friendship);

        $body = $request->input('body');

        $request->merge(['body' => is_string($body) ? trim($body) : $body]);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
        ]);

        $message = Message::sendFrom($me, $friend, $data['body']);

        abort_if($message === null, 404);

        $this->sendQuietly(function () use ($me, $friend, $friendship): void {
            if (! $friend->is_active) {
                return;
            }

            $key = 'member-message-notify:'.$friend->getKey().':'.$me->getKey();

            if (RateLimiter::tooManyAttempts($key, 1)) {
                return;
            }

            $friend->notify(
                (new NewMessageNotification($me, route('member.messages.show', $friendship)))->locale('zh_TW'),
            );

            RateLimiter::hit($key, 900);
        });

        return redirect($this->localizedRoute('member.messages.show', ['friendship' => $friendship]));
    }

    /**
     * @return array{0: Member, 1: Member}
     */
    private function conversationFor(Friendship $friendship): array
    {
        $me = $this->guard()->user();

        abort_unless($friendship->isParticipant($me) && $friendship->isAccepted(), 404);

        $friend = $friendship->otherMember($me);

        abort_unless($me->canMessage($friend), 404);

        return [$me, $friend];
    }
}

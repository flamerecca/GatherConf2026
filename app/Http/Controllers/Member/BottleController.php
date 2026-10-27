<?php

namespace App\Http\Controllers\Member;

use App\Models\DriftBottle;
use App\Models\Member;
use App\Notifications\DriftBottleReceivedNotification;
use App\Notifications\DriftBottleRepliedNotification;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class BottleController extends MemberController
{
    public function index(): View
    {
        $me = $this->guard()->user();

        return view('member.bottles', [
            'received' => DriftBottle::query()->receivedBy($me)->latest('assigned_at')->get(),
            'sent' => DriftBottle::query()->sentBy($me)->latest()->get(),
            'holding' => DriftBottle::heldCount($me),
            'throwsLeft' => max(0, DriftBottle::DAILY_THROW_LIMIT - DriftBottle::throwsToday($me)),
            'picksLeft' => DriftBottle::picksLeft($me),
        ]);
    }

    public function create(): View
    {
        return view('member.bottle-create');
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $this->guard()->user();

        $this->trimBody($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.DriftBottle::MAX_LENGTH, $this->noContactInfo()],
            'agree_conduct' => ['accepted'],
        ]);

        $bottle = $this->serialized($me, 'body', function () use ($me, $data): DriftBottle {
            if (DriftBottle::throwsToday($me) >= DriftBottle::DAILY_THROW_LIMIT) {
                throw ValidationException::withMessages(['body' => __('bottles.errors.daily_limit')]);
            }

            return DriftBottle::throwFrom($me, $data['body']);
        });

        if ($bottle->recipient !== null) {
            $this->notifyReceived($bottle);
        }

        return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.thrown'));
    }

    public function pick(): RedirectResponse
    {
        $me = $this->guard()->user();

        $bottle = $this->serialized($me, 'pick', function () use ($me): ?DriftBottle {
            if (DriftBottle::heldCount($me) >= DriftBottle::HOLD_LIMIT) {
                throw ValidationException::withMessages(['pick' => __('bottles.errors.holding')]);
            }

            $key = DriftBottle::pickKey($me);

            if (RateLimiter::tooManyAttempts($key, DriftBottle::DAILY_PICK_LIMIT)) {
                throw ValidationException::withMessages(['pick' => __('bottles.errors.pick_limit')]);
            }

            RateLimiter::hit($key, 86400);

            $bottle = DriftBottle::pick($me);

            if ($bottle === null) {
                RateLimiter::decrement($key);
            }

            return $bottle;
        });

        if ($bottle === null) {
            return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.empty_sea'));
        }

        return redirect($this->localizedRoute('member.bottles.show', ['bottle' => $bottle]));
    }

    public function show(DriftBottle $bottle): View
    {
        $me = $this->guard()->user();
        $isRecipient = $bottle->recipient_id === $me->getKey();
        $isSender = $bottle->sender_id === $me->getKey();

        abort_unless($isSender || $isRecipient, 404);
        abort_if(! $isSender && $bottle->removed_at !== null, 404);

        if ($isRecipient) {
            $bottle->open($me);
            $bottle->refresh();
        }

        return view('member.bottle-show', [
            'bottle' => $bottle,
            'isRecipient' => $isRecipient,
        ]);
    }

    public function reply(Request $request, DriftBottle $bottle): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($bottle->recipient_id === $me->getKey() && $bottle->removed_at === null, 404);

        $this->trimBody($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.DriftBottle::MAX_LENGTH, $this->noContactInfo()],
        ]);

        if (! $bottle->replyFrom($me, $data['body'])) {
            throw ValidationException::withMessages(['body' => __('bottles.errors.cannot_reply')]);
        }

        if ($bottle->sender !== null) {
            $this->sendQuietly(function () use ($bottle): void {
                $bottle->sender->notify(
                    (new DriftBottleRepliedNotification(route('member.bottles.show', $bottle)))->locale('zh_TW'),
                );
            });
        }

        return redirect($this->localizedRoute('member.bottles.show', ['bottle' => $bottle]));
    }

    public function report(Request $request, DriftBottle $bottle): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($bottle->sender_id === $me->getKey() || $bottle->recipient_id === $me->getKey(), 404);

        $data = $request->validate([
            'target' => ['required', 'in:bottle,reply'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if (! $bottle->reportBy($me, $data['target'], $data['reason'] ?? null)) {
            throw ValidationException::withMessages(['target' => __('bottles.errors.cannot_report')]);
        }

        return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.reported'));
    }

    /**
     * Run the check and the write for one member one at a time so the limits cannot be raced.
     * Waiting too long for the lock is shown as a validation error on the given field.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function serialized(Member $member, string $errorField, Closure $callback): mixed
    {
        try {
            return Cache::lock('bottle-member:'.$member->getKey(), 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([$errorField => __('bottles.errors.busy')]);
        }
    }

    private function noContactInfo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && DriftBottle::containsContactInfo($value)) {
                $fail(__('bottles.errors.contact'));
            }
        };
    }

    private function trimBody(Request $request): void
    {
        $body = $request->input('body');

        $request->merge(['body' => is_string($body) ? trim($body) : $body]);
    }

    private function notifyReceived(DriftBottle $bottle): void
    {
        $key = 'bottle-notify:'.$bottle->recipient_id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        $this->sendQuietly(function () use ($bottle, $key): void {
            $bottle->recipient->notify(
                (new DriftBottleReceivedNotification(route('member.bottles.show', $bottle)))->locale('zh_TW'),
            );

            RateLimiter::hit($key, 900);
        });
    }
}

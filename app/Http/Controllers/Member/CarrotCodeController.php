<?php

namespace App\Http\Controllers\Member;

use App\Models\AuthorizationCode;
use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CarrotCodeController extends MemberController
{
    private const MAX_WRONG_CODES = 5;

    private const LOCK_SECONDS = 600;

    public function redeem(Request $request): RedirectResponse
    {
        $member = $this->guard()->user();

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $failKey = 'member-code-fail:'.$member->getKey();

        if (RateLimiter::tooManyAttempts($failKey, self::MAX_WRONG_CODES)) {
            throw ValidationException::withMessages([
                'code' => __('carrots.code.locked', ['minutes' => (int) ceil(RateLimiter::availableIn($failKey) / 60)]),
            ]);
        }

        $input = AuthorizationCode::normalize($data['code']);

        $task = $input === '' ? null : CarrotTask::query()->where('short_code', $input)->first();
        $code = $task === null && $input !== '' ? AuthorizationCode::query()->matching($input)->first() : null;

        if ($task === null && $code === null) {
            RateLimiter::hit($failKey, self::LOCK_SECONDS);

            throw ValidationException::withMessages(['code' => __('carrots.code.invalid')]);
        }

        [$state, $reward] = $task !== null ? $this->completeTask($task) : [$code->redeem($member), $code->reward];

        if ($state !== AuthorizationCode::STATE_EARNED) {
            throw ValidationException::withMessages(['code' => __('carrots.code.'.$state)]);
        }

        return redirect($this->localizedRoute('member.tasks'))
            ->with('status', __('carrots.code.earned', ['count' => $reward]));
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function completeTask(CarrotTask $task): array
    {
        if (! $task->isOpen()) {
            return [AuthorizationCode::STATE_CLOSED, $task->reward];
        }

        $earned = CarrotLedger::award(
            $this->guard()->user(),
            CarrotLedger::REASON_TASK,
            $task->reward,
            $task->dedupeKeyFor($this->guard()->user()),
            $task,
        );

        return [$earned ? AuthorizationCode::STATE_EARNED : AuthorizationCode::STATE_DONE, $task->reward];
    }
}

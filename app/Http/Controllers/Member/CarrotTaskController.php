<?php

namespace App\Http\Controllers\Member;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use Illuminate\Contracts\View\View;

class CarrotTaskController extends MemberController
{
    public function index(): View
    {
        abort(501);
    }

    public function complete(string $code): View
    {
        $member = $this->guard()->user();
        $task = CarrotTask::query()->where('code', $code)->firstOrFail();

        if (! $task->isOpen()) {
            return $this->result('closed', $task);
        }

        $earned = CarrotLedger::award(
            $member,
            CarrotLedger::REASON_TASK,
            $task->reward,
            $task->dedupeKeyFor($member),
            $task,
        );

        return $this->result($earned ? 'earned' : 'done', $task);
    }

    private function result(string $state, CarrotTask $task): View
    {
        return view('member.task-result', [
            'state' => $state,
            'task' => $task,
            'carrots' => $this->guard()->user()->fresh()->carrots,
        ]);
    }
}

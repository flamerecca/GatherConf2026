<?php

namespace App\Http\Controllers\Member;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\CarrotTransaction;
use Illuminate\Contracts\View\View;

class CarrotTaskController extends MemberController
{
    public function index(): View
    {
        $member = $this->guard()->user();

        $tasks = CarrotTask::query()->open()->orderBy('created_at')->get();

        $keys = collect(CarrotLedger::siteRewards())
            ->keys()
            ->map(fn (string $code) => CarrotLedger::siteDedupeKey($code, $member))
            ->merge($tasks->map(fn (CarrotTask $task) => $task->dedupeKeyFor($member)));

        $done = CarrotTransaction::query()
            ->where('member_id', $member->getKey())
            ->whereIn('dedupe_key', $keys)
            ->pluck('dedupe_key')
            ->flip();

        return view('member.tasks', [
            'member' => $member,
            'siteTasks' => collect(CarrotLedger::siteRewards())->map(fn (int $reward, string $code) => [
                'code' => $code,
                'reward' => $reward,
                'done' => $done->has(CarrotLedger::siteDedupeKey($code, $member)),
            ]),
            'fieldTasks' => $tasks->map(fn (CarrotTask $task) => [
                'task' => $task,
                'done' => $done->has($task->dedupeKeyFor($member)),
            ]),
        ]);
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

<?php

namespace Tests\Feature\Carrots;

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\CarrotTaskCompletionsRelationManager;
use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCompletionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    private function manager(Member $member)
    {
        return Livewire::test(CarrotTaskCompletionsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ]);
    }

    public function test_it_lists_every_task_with_its_completion_state(): void
    {
        $member = Member::factory()->create();
        $done = CarrotTask::factory()->create();
        $todo = CarrotTask::factory()->create();
        CarrotLedger::award($member, CarrotLedger::REASON_TASK, $done->reward, $done->dedupeKeyFor($member), $done);

        $this->manager($member)
            ->assertCanSeeTableRecords([$done, $todo])
            ->filterTable('is_completed', true)
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$todo]);
    }

    public function test_an_admin_can_mark_a_task_completed_once(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 4, 'is_active' => false]);

        $this->manager($member)
            ->callAction(TestAction::make('markCompleted')->table($task))
            ->assertNotified('已標記為完成')
            ->assertActionHidden(TestAction::make('markCompleted')->table($task));

        $this->assertSame(4, $member->fresh()->carrots);

        $this->assertFalse(CarrotLedger::award($member, CarrotLedger::REASON_TASK, 4, $task->dedupeKeyFor($member), $task));
        $this->assertSame(4, $member->fresh()->carrots);
    }

    public function test_completion_is_tracked_per_member(): void
    {
        $task = CarrotTask::factory()->create();
        $other = Member::factory()->create();
        CarrotLedger::award($other, CarrotLedger::REASON_TASK, $task->reward, $task->dedupeKeyFor($other), $task);

        $this->manager(Member::factory()->create())
            ->assertActionVisible(TestAction::make('markCompleted')->table($task));
    }
}

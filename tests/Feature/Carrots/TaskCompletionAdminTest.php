<?php

namespace Tests\Feature\Carrots;

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\CarrotSiteTaskCompletionsRelationManager;
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

    public function test_an_admin_can_revoke_a_completion_and_the_carrots_are_taken_back(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 4]);
        CarrotLedger::award($member, CarrotLedger::REASON_TASK, 4, $task->dedupeKeyFor($member), $task);

        $this->manager($member)
            ->assertActionVisible(TestAction::make('revokeCompletion')->table($task))
            ->callAction(TestAction::make('revokeCompletion')->table($task))
            ->assertNotified('已取消完成')
            ->assertActionHidden(TestAction::make('revokeCompletion')->table($task))
            ->assertActionHidden(TestAction::make('markCompleted')->table($task));

        $this->assertSame(0, $member->fresh()->carrots);
        $this->assertSame(2, $member->carrotTransactions()->count());
        $this->assertNull(CarrotLedger::revokeTask($member, $task));
        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_a_revoked_task_counts_as_not_completed_in_the_filter(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create();
        CarrotLedger::award($member, CarrotLedger::REASON_TASK, $task->reward, $task->dedupeKeyFor($member), $task);
        CarrotLedger::revokeTask($member, $task);

        $this->manager($member)
            ->filterTable('is_completed', false)
            ->assertCanSeeTableRecords([$task])
            ->filterTable('is_completed', true)
            ->assertCanNotSeeTableRecords([$task]);
    }

    public function test_revoking_can_leave_the_member_with_a_negative_balance(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 4]);
        CarrotLedger::award($member, CarrotLedger::REASON_TASK, 4, $task->dedupeKeyFor($member), $task);
        CarrotLedger::adjust($member, -3, '兌換');

        $this->manager($member)
            ->callAction(TestAction::make('revokeCompletion')->table($task))
            ->assertNotified('已取消完成');

        $this->assertSame(-3, $member->fresh()->carrots);
    }

    private function siteManager(Member $member)
    {
        return Livewire::test(CarrotSiteTaskCompletionsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ]);
    }

    public function test_the_default_site_tasks_are_listed(): void
    {
        $this->siteManager(Member::factory()->create())
            ->assertSee('驗證 Email')
            ->assertSee('完成人格測驗')
            ->assertActionVisible(TestAction::make('markCompleted')->table('verify_email'));
    }

    public function test_an_admin_can_complete_and_revoke_a_site_task(): void
    {
        $member = Member::factory()->create();
        $reward = CarrotLedger::siteRewards()['set_password'];

        $this->siteManager($member)
            ->callAction(TestAction::make('markCompleted')->table('set_password'))
            ->assertNotified('已標記為完成');

        $this->siteManager($member)
            ->assertActionHidden(TestAction::make('markCompleted')->table('set_password'))
            ->assertActionVisible(TestAction::make('revokeCompletion')->table('set_password'));

        $this->assertSame($reward, $member->fresh()->carrots);
        $this->assertDatabaseHas('carrot_transactions', ['member_id' => $member->id, 'reason' => 'set_password', 'amount' => $reward]);

        $this->siteManager($member)
            ->callAction(TestAction::make('revokeCompletion')->table('set_password'))
            ->assertNotified('已取消完成');

        $this->siteManager($member)
            ->assertActionHidden(TestAction::make('revokeCompletion')->table('set_password'))
            ->assertActionHidden(TestAction::make('markCompleted')->table('set_password'));

        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_a_site_task_the_member_already_earned_cannot_be_marked_again(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::award($member, 'complete_quiz', 5, CarrotLedger::siteDedupeKey('complete_quiz', $member));

        $this->siteManager($member)
            ->assertActionHidden(TestAction::make('markCompleted')->table('complete_quiz'))
            ->assertActionVisible(TestAction::make('revokeCompletion')->table('complete_quiz'));
    }
}

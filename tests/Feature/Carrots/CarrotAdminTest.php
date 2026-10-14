<?php

namespace Tests\Feature\Carrots;

use App\Filament\Resources\CarrotTasks\Pages\CreateCarrotTask;
use App\Filament\Resources\CarrotTasks\Pages\EditCarrotTask;
use App\Filament\Resources\CarrotTasks\Pages\ViewCarrotTask;
use App\Filament\Resources\CarrotTransactions\CarrotTransactionResource;
use App\Filament\Resources\CarrotTransactions\Pages\ListCarrotTransactions;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\CarrotTransactionsRelationManager;
use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\CarrotTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CarrotAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_the_task_can_be_created_without_choosing_a_code(): void
    {
        Livewire::test(CreateCarrotTask::class)
            ->fillForm(['name' => '攤位打卡', 'description' => '掃描', 'reward' => 3, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = CarrotTask::query()->firstOrFail();
        $this->assertSame('攤位打卡', $task->name);
        $this->assertSame(32, strlen($task->code));
    }

    public function test_the_reward_must_be_a_positive_integer(): void
    {
        Livewire::test(CreateCarrotTask::class)
            ->fillForm(['name' => 'x', 'reward' => 0])
            ->call('create')
            ->assertHasFormErrors(['reward']);
    }

    public function test_the_code_cannot_be_edited(): void
    {
        $task = CarrotTask::factory()->create();
        $code = $task->code;

        Livewire::test(EditCarrotTask::class, ['record' => $task->getKey()])
            ->fillForm(['name' => '新名稱'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($code, $task->fresh()->code);
        $this->assertSame('新名稱', $task->fresh()->name);
    }

    public function test_the_view_page_shows_the_scan_url_and_a_qr_code(): void
    {
        $task = CarrotTask::factory()->create();

        Livewire::test(ViewCarrotTask::class, ['record' => $task->getKey()])
            ->assertOk()
            ->assertSee($task->code)
            ->assertSeeHtml('data:image/svg+xml');
    }

    public function test_the_transaction_list_is_read_only_and_filterable(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::award($member, 'verify_email', 5, 'k1');
        CarrotLedger::adjust($member, 2, '補發');

        Livewire::test(ListCarrotTransactions::class)
            ->assertOk()
            ->assertCanSeeTableRecords(CarrotTransaction::all())
            ->filterTable('reason', 'manual')
            ->assertCanSeeTableRecords(CarrotTransaction::query()->where('reason', 'manual')->get())
            ->assertCanNotSeeTableRecords(CarrotTransaction::query()->where('reason', 'verify_email')->get());

        $this->assertFalse(CarrotTransactionResource::canCreate());
    }

    public function test_the_adjust_action_adds_carrots_with_a_reason(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('adjustCarrots', ['amount' => 5, 'note' => '活動獎勵'])
            ->assertNotified();

        $this->assertSame(5, $member->fresh()->carrots);
        $this->assertDatabaseHas('carrot_transactions', ['member_id' => $member->id, 'note' => '活動獎勵', 'created_by' => auth()->id()]);
    }

    public function test_the_adjust_action_rejects_going_below_zero(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('adjustCarrots', ['amount' => -1, 'note' => '扣除'])
            ->assertNotified();

        $this->assertSame(0, $member->fresh()->carrots);
        $this->assertSame(0, CarrotTransaction::query()->count());
    }

    public function test_the_adjust_action_requires_a_reason_and_a_non_zero_amount(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('adjustCarrots', ['amount' => 0, 'note' => ''])
            ->assertHasActionErrors(['amount', 'note']);

        $this->assertSame(0, CarrotTransaction::query()->count());
    }

    public function test_the_member_page_shows_the_balance_and_history(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::adjust($member, 4, '補發');

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertSee('胡蘿蔔')
            ->assertSeeLivewire(CarrotTransactionsRelationManager::class);
    }
}

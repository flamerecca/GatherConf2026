<?php

namespace Tests\Feature\Carrots;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\CarrotTransaction;
use App\Models\Member;
use DomainException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CarrotLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_award_writes_a_transaction_and_updates_the_balance(): void
    {
        $member = Member::factory()->create();

        $this->assertTrue(CarrotLedger::award($member, 'verify_email', 5, 'verify_email:'.$member->id));

        $this->assertSame(5, $member->carrots);
        $this->assertSame(5, $member->fresh()->carrots);
        $this->assertDatabaseHas('carrot_transactions', [
            'member_id' => $member->id,
            'amount' => 5,
            'reason' => 'verify_email',
            'dedupe_key' => 'verify_email:'.$member->id,
        ]);
    }

    public function test_the_same_dedupe_key_is_granted_only_once(): void
    {
        $member = Member::factory()->create();

        $this->assertTrue(CarrotLedger::award($member, 'verify_email', 5, 'k'));
        $this->assertFalse(CarrotLedger::award($member, 'verify_email', 5, 'k'));

        $this->assertSame(5, $member->fresh()->carrots);
        $this->assertSame(1, CarrotTransaction::query()->count());
    }

    public function test_a_non_positive_award_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CarrotLedger::award(Member::factory()->create(), 'x', 0, 'k');
    }

    public function test_adjust_can_reach_zero_but_not_go_below(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::award($member, 'x', 3, 'k');

        CarrotLedger::adjust($member, -3, '扣除', 1);
        $this->assertSame(0, $member->fresh()->carrots);

        try {
            CarrotLedger::adjust($member, -1, '再扣', 1);
            $this->fail('Expected DomainException.');
        } catch (DomainException) {
        }

        $this->assertSame(0, $member->fresh()->carrots);
        $this->assertSame(2, CarrotTransaction::query()->count());
    }

    public function test_each_adjustment_is_its_own_record_with_note_and_actor(): void
    {
        $member = Member::factory()->create();

        CarrotLedger::adjust($member, 2, '活動獎勵', 7);
        CarrotLedger::adjust($member, 2, '活動獎勵', 7);

        $this->assertSame(4, $member->fresh()->carrots);
        $this->assertDatabaseHas('carrot_transactions', ['member_id' => $member->id, 'note' => '活動獎勵', 'created_by' => 7, 'reason' => 'manual']);
        $this->assertSame(2, $member->carrotTransactions()->count());
    }

    public function test_balance_equals_the_ledger_sum(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::award($member, 'a', 5, 'k1');
        CarrotLedger::award($member, 'b', 3, 'k2');
        CarrotLedger::adjust($member, -4, 'x');

        $this->assertSame(4, CarrotLedger::balance($member));
        $this->assertSame(4, $member->fresh()->carrots);
    }

    public function test_transactions_are_append_only(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::award($member, 'a', 5, 'k1');
        $transaction = CarrotTransaction::query()->firstOrFail();

        $this->assertFalse($transaction->update(['amount' => 99]));
        $this->assertFalse($transaction->delete());
        $this->assertSame(5, $transaction->fresh()->amount);
    }

    public function test_deleting_a_task_keeps_the_transactions(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create();
        CarrotLedger::award($member, 'task', 3, $task->dedupeKeyFor($member), $task);

        $task->delete();

        $this->assertNull(CarrotTransaction::query()->firstOrFail()->task_id);
    }

    public function test_task_open_window(): void
    {
        $this->assertTrue(CarrotTask::factory()->create()->isOpen());
        $this->assertFalse(CarrotTask::factory()->inactive()->create()->isOpen());
        $this->assertFalse(CarrotTask::factory()->notStarted()->create()->isOpen());
        $this->assertFalse(CarrotTask::factory()->ended()->create()->isOpen());
        $this->assertCount(1, CarrotTask::query()->open()->get());
    }

    public function test_a_task_gets_an_unguessable_code(): void
    {
        $a = CarrotTask::factory()->create();
        $b = CarrotTask::factory()->create();

        $this->assertSame(32, strlen($a->code));
        $this->assertNotSame($a->code, $b->code);
    }

    public function test_a_zero_reward_is_skipped_without_reporting_an_error(): void
    {
        $handler = $this->spy(ExceptionHandler::class);
        $member = Member::factory()->create();

        $this->assertFalse(CarrotLedger::awardQuietly($member, 'x', 0, 'k'));

        $handler->shouldNotHaveReceived('report');
    }

    public function test_a_balance_beyond_the_column_limit_is_refused(): void
    {
        $member = Member::factory()->create();
        Member::query()->whereKey($member->id)->update(['carrots' => CarrotLedger::MAX_BALANCE]);

        $this->expectException(DomainException::class);

        CarrotLedger::award($member->fresh(), 'x', 1, 'k');
    }
}

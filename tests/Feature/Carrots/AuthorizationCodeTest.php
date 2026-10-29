<?php

namespace Tests\Feature\Carrots;

use App\Filament\Resources\AuthorizationCodes\Pages\CreateAuthorizationCode;
use App\Filament\Resources\AuthorizationCodes\Pages\ListAuthorizationCodes;
use App\Models\AuthorizationCode;
use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\CarrotTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationCodeTest extends TestCase
{
    use RefreshDatabase;

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    private function redeem(Member $member, string $code, string $route = 'member.tasks.redeem'): TestResponse
    {
        return $this->asMember($member)->post(route($route), ['code' => $code]);
    }

    public function test_the_task_page_has_a_form_for_the_code(): void
    {
        $this->asMember(Member::factory()->create())->get(route('member.tasks'))
            ->assertOk()
            ->assertSee('data-code-form', false)
            ->assertSee('輸入授權碼');

        $this->asMember(Member::factory()->create())->get(route('member.tasks.en'))->assertSee('Enter an access code');
    }

    public function test_a_code_is_generated_when_none_is_given(): void
    {
        $code = AuthorizationCode::factory()->create();

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code->code);
    }

    public function test_redeeming_a_code_grants_its_carrots_ignoring_case_and_separators(): void
    {
        $member = Member::factory()->create();
        $code = AuthorizationCode::factory()->create(['code' => 'ABCD1234', 'reward' => 7]);

        $this->redeem($member, ' abcd-1234 ')
            ->assertRedirect(route('member.tasks'))
            ->assertSessionHas('status', '兌換成功，獲得 7 根胡蘿蔔！');

        $this->assertSame(7, $member->fresh()->carrots);
        $transaction = CarrotTransaction::query()->sole();
        $this->assertSame(CarrotLedger::REASON_CODE, $transaction->reason);
        $this->assertSame($code->id, $transaction->authorization_code_id);
    }

    public function test_the_english_form_returns_to_the_english_task_page(): void
    {
        $member = Member::factory()->create();
        AuthorizationCode::factory()->create(['code' => 'ABCD1234']);

        $this->redeem($member, 'ABCD1234', 'member.tasks.redeem.en')->assertRedirect(route('member.tasks.en'));
    }

    public function test_a_member_can_use_one_code_only_once(): void
    {
        $member = Member::factory()->create();
        AuthorizationCode::factory()->create(['code' => 'ABCD1234', 'reward' => 7]);

        $this->redeem($member, 'ABCD1234');
        $this->redeem($member, 'ABCD1234')->assertSessionHasErrors(['code' => '你已經使用過這組授權碼了。']);

        $this->assertSame(7, $member->fresh()->carrots);
    }

    public function test_a_code_with_a_use_limit_stops_after_the_limit_across_members(): void
    {
        AuthorizationCode::factory()->limitedTo(2)->create(['code' => 'ABCD1234']);
        $members = Member::factory()->count(3)->create();

        $this->redeem($members[0], 'ABCD1234')->assertSessionHasNoErrors();
        $this->redeem($members[1], 'ABCD1234')->assertSessionHasNoErrors();
        $this->redeem($members[2], 'ABCD1234')->assertSessionHasErrors(['code' => '這組授權碼已經被兌換完了。']);

        $this->assertSame(0, $members[2]->fresh()->carrots);
    }

    public function test_closed_codes_cannot_be_redeemed(): void
    {
        $member = Member::factory()->create();
        AuthorizationCode::factory()->inactive()->create(['code' => 'OFF11111']);
        AuthorizationCode::factory()->notStarted()->create(['code' => 'SOON2222']);
        AuthorizationCode::factory()->ended()->create(['code' => 'OLD33333']);

        foreach (['OFF11111', 'SOON2222', 'OLD33333'] as $code) {
            $this->redeem($member, $code)->assertSessionHasErrors(['code' => '這組授權碼目前不在可使用的期間內。']);
        }

        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_a_wrong_code_is_rejected_and_too_many_wrong_codes_lock_the_member(): void
    {
        $member = Member::factory()->create();
        AuthorizationCode::factory()->create(['code' => 'ABCD1234']);

        for ($i = 0; $i < 5; $i++) {
            $this->redeem($member, 'WRONG'.$i)->assertSessionHasErrors(['code' => '找不到這組授權碼，請確認後再試一次。']);
        }

        $this->redeem($member, 'ABCD1234')->assertSessionHasErrors('code');
        $this->assertSame(0, $member->fresh()->carrots);

        $other = Member::factory()->create();
        $this->redeem($other, 'ABCD1234')->assertSessionHasNoErrors();
    }

    public function test_a_field_task_short_code_completes_that_task_once(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 4]);

        $this->assertNotEmpty($task->short_code);

        $this->redeem($member, strtolower($task->short_code))->assertRedirect(route('member.tasks'));
        $this->redeem($member, $task->short_code)->assertSessionHasErrors('code');

        $this->assertSame(4, $member->fresh()->carrots);
        $this->assertSame($task->id, CarrotTransaction::query()->sole()->task_id);
    }

    public function test_a_closed_field_task_short_code_grants_nothing(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->inactive()->create();

        $this->redeem($member, $task->short_code)->assertSessionHasErrors(['code' => '這組授權碼目前不在可使用的期間內。']);

        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_guests_and_empty_input_are_rejected(): void
    {
        $this->post(route('member.tasks.redeem'), ['code' => 'ABCD1234'])->assertRedirect();

        $this->redeem(Member::factory()->create(), '')->assertSessionHasErrors('code');
        $this->redeem(Member::factory()->create(), '---')->assertSessionHasErrors('code');
    }

    public function test_admins_can_create_and_list_codes(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));

        Livewire::test(CreateAuthorizationCode::class)
            ->fillForm(['name' => '現場活動', 'reward' => 10, 'max_uses' => 50])
            ->call('create')
            ->assertHasNoFormErrors();

        $code = AuthorizationCode::query()->sole();
        $this->assertSame('現場活動', $code->name);
        $this->assertSame(8, strlen($code->code));

        Livewire::test(ListAuthorizationCodes::class)->assertCanSeeTableRecords([$code]);
    }

    public function test_a_custom_code_is_normalized_and_cannot_clash_with_a_task_short_code(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
        $task = CarrotTask::factory()->create();

        Livewire::test(CreateAuthorizationCode::class)
            ->fillForm(['name' => '撞碼', 'reward' => 1, 'code' => $task->short_code])
            ->call('create')
            ->assertHasFormErrors(['code']);

        Livewire::test(CreateAuthorizationCode::class)
            ->fillForm(['name' => '自訂', 'reward' => 1, 'code' => 'gather-2026'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('GATHER2026', AuthorizationCode::query()->sole()->code);
    }
}

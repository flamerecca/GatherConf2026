<?php

namespace Tests\Feature\Carrots;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    public function test_the_list_shows_every_site_behaviour_with_its_reward(): void
    {
        $member = Member::factory()->create();

        $response = $this->asMember($member)->get(route('member.tasks'))->assertOk();

        foreach (config('carrots.site') as $code => $reward) {
            $response->assertSee(__("carrots.site.$code.name"), false)
                ->assertSee(__("carrots.site.$code.description"), false)
                ->assertSee('data-site-task="'.$code.'"', false)
                ->assertSee('data-reward="'.$reward.'"', false);
        }
    }

    public function test_done_and_todo_states_follow_the_ledger(): void
    {
        $member = Member::factory()->unverified()->create();
        $member->markEmailAsVerified();

        $this->asMember($member->fresh())->get(route('member.tasks'))
            ->assertSee('data-site-task="verify_email" data-state="done"', false)
            ->assertSee('data-site-task="set_password" data-state="todo"', false);
    }

    public function test_done_and_todo_tasks_get_different_colours(): void
    {
        $member = Member::factory()->unverified()->create();
        $member->markEmailAsVerified();

        $html = $this->asMember($member->fresh())->get(route('member.tasks'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-site-task="verify_email"[^>]*class="[^"]*bg-moss\/10/', $html);
        $this->assertMatchesRegularExpression('/data-site-task="set_password"[^>]*class="[^"]*border-copper\/50/', $html);
        $this->assertStringContainsString('✓ '.__('carrots.list.done'), $html);
    }

    public function test_only_open_field_tasks_are_listed_and_their_code_is_never_shown(): void
    {
        $member = Member::factory()->create();
        $open = CarrotTask::factory()->create(['name' => '攤位打卡', 'description' => '到攤位掃描']);
        $closed = [
            CarrotTask::factory()->inactive()->create(['name' => '停用任務']),
            CarrotTask::factory()->notStarted()->create(['name' => '未開始任務']),
            CarrotTask::factory()->ended()->create(['name' => '已結束任務']),
        ];

        $response = $this->asMember($member)->get(route('member.tasks'))
            ->assertOk()
            ->assertSee('攤位打卡')
            ->assertSee('到攤位掃描')
            ->assertDontSee($open->code)
            ->assertDontSee('/member/tasks/'.$open->code);

        foreach ($closed as $task) {
            $response->assertDontSee($task->name)->assertDontSee($task->code);
        }
    }

    public function test_a_field_task_shows_done_after_scanning(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create();

        $this->asMember($member)->get($task->completeUrl('zh_TW'));

        $this->asMember($member)->get(route('member.tasks'))
            ->assertSee('data-field-task="'.$task->id.'" data-state="done"', false);
    }

    public function test_the_header_shows_the_total(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::adjust($member, 7, '測試');

        $this->asMember($member->fresh())->get(route('member.tasks'))
            ->assertSee('data-carrots="7"', false);
    }

    public function test_a_negative_total_is_shown_in_bold_red(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::adjust($member, -2, '測試');

        $this->asMember($member->fresh())->get(route('member.tasks'))
            ->assertSee('data-carrots="-2"', false)
            ->assertSee('text-red-700 dark:text-red-400 font-bold', false);

        $this->asMember($member->fresh())->get(route('member.center'))
            ->assertSee('text-red-700 dark:text-red-400 font-bold', false);
    }

    public function test_a_positive_total_is_not_shown_in_red(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::adjust($member, 2, '測試');

        $html = $this->asMember($member->fresh())->get(route('member.tasks'))->getContent();

        $this->assertSame(1, preg_match('/<p data-carrots="2"[^>]*>/', $html, $balance));
        $this->assertStringNotContainsString('text-red-700', $balance[0]);
    }

    public function test_guests_and_unverified_members_are_redirected(): void
    {
        $this->get(route('member.tasks'))->assertRedirect(route('member.login'));

        $this->asMember(Member::factory()->unverified()->create())
            ->get(route('member.tasks'))
            ->assertRedirect(route('member.verification.notice'));
    }

    public function test_the_english_list_is_in_english_and_noindex(): void
    {
        $member = Member::factory()->create();

        $this->asMember($member)->get(route('member.tasks.en'))
            ->assertOk()
            ->assertSee(trans('carrots.list.heading', [], 'en'), false)
            ->assertSee('noindex', false);
    }

    public function test_the_member_center_links_to_the_list_with_the_total(): void
    {
        $member = Member::factory()->create();
        CarrotLedger::adjust($member, 3, '測試');

        $this->asMember($member->fresh())->get(route('member.center'))
            ->assertSee('data-carrots="3"', false)
            ->assertSee(route('member.tasks'), false);
    }

    public function test_the_headings_are_collapsible_and_quiz_and_friend_tasks_share_one_group(): void
    {
        $html = $this->actingAs(Member::factory()->create(), 'member')->get('/member/tasks')->assertOk()->getContent();

        foreach (['site', 'site-account', 'site-social', 'field'] as $name) {
            $this->assertStringContainsString('<details open', substr($html, strpos($html, 'data-collapsible="'.$name.'"') - 40, 60));
        }

        $social = substr($html, strpos($html, 'data-collapsible="site-social"'), strpos($html, 'data-collapsible="field"') - strpos($html, 'data-collapsible="site-social"'));
        foreach (['complete_quiz', 'first_friend', 'first_message'] as $code) {
            $this->assertStringContainsString('data-site-task="'.$code.'"', $social);
        }
        $this->assertStringNotContainsString('data-site-task="verify_email"', $social);
    }
}

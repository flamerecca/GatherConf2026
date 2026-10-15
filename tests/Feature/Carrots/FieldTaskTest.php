<?php

namespace Tests\Feature\Carrots;

use App\Models\CarrotTask;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FieldTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('member-task');
    }

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    public function test_scanning_awards_the_reward_once(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 4]);

        $this->asMember($member)->get($task->completeUrl('zh_TW'))
            ->assertOk()
            ->assertSee(__('carrots.result.earned', ['reward' => 4]), false);

        $this->assertSame(4, $member->fresh()->carrots);

        $this->asMember($member)->get($task->completeUrl('zh_TW'))
            ->assertOk()
            ->assertSee(__('carrots.result.done'), false);

        $this->assertSame(4, $member->fresh()->carrots);
    }

    public function test_a_guest_is_sent_to_login_and_returns_to_the_task(): void
    {
        $task = CarrotTask::factory()->create();

        $this->get($task->completeUrl('zh_TW'))->assertRedirect(route('member.login'));
        $this->assertSame($task->completeUrl('zh_TW'), session('url.intended'));
    }

    public function test_a_closed_task_awards_nothing(): void
    {
        $member = Member::factory()->create();

        foreach ([CarrotTask::factory()->inactive(), CarrotTask::factory()->notStarted(), CarrotTask::factory()->ended()] as $factory) {
            $task = $factory->create();

            $this->asMember($member)->get($task->completeUrl('zh_TW'))
                ->assertOk()
                ->assertSee(__('carrots.result.closed'), false);
        }

        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_an_unknown_code_is_a_404(): void
    {
        $this->asMember(Member::factory()->create())
            ->get(route('member.tasks.complete', ['code' => str_repeat('a', 32)]))
            ->assertNotFound();
    }

    public function test_an_unverified_member_is_redirected_and_awarded_nothing(): void
    {
        $member = Member::factory()->unverified()->create();
        $task = CarrotTask::factory()->create();

        $this->asMember($member)->get($task->completeUrl('zh_TW'))
            ->assertRedirect(route('member.verification.notice'));
        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_an_inactive_member_cannot_scan(): void
    {
        $member = Member::factory()->inactive()->create();
        $task = CarrotTask::factory()->create();

        $this->asMember($member)->get($task->completeUrl('zh_TW'))
            ->assertRedirect(route('member.login'));

        $this->assertGuest('member');
        $this->assertSame(0, $member->fresh()->carrots);
    }

    public function test_scanning_is_rate_limited(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->asMember($member)->get($task->completeUrl('zh_TW'))->assertOk();
        }

        $this->asMember($member)->get($task->completeUrl('zh_TW'))->assertStatus(429);
    }

    public function test_the_english_page_is_in_english(): void
    {
        $member = Member::factory()->create();
        $task = CarrotTask::factory()->create(['reward' => 2]);

        $this->asMember($member)->get($task->completeUrl('en'))
            ->assertOk()
            ->assertSee(trans('carrots.result.earned', ['reward' => 2], 'en'), false);
    }

    public function test_two_members_each_earn_from_the_same_task(): void
    {
        $task = CarrotTask::factory()->create(['reward' => 3]);
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->asMember($a)->get($task->completeUrl('zh_TW'))->assertOk();
        $this->asMember($b)->get($task->completeUrl('zh_TW'))->assertOk();

        $this->assertSame(3, $a->fresh()->carrots);
        $this->assertSame(3, $b->fresh()->carrots);
    }

    public function test_an_unverified_member_returns_to_the_task_after_verifying(): void
    {
        $member = Member::factory()->unverified()->create();
        $task = CarrotTask::factory()->create();

        $this->asMember($member)->get($task->completeUrl('zh_TW'))->assertRedirect();
        $this->assertSame($task->completeUrl('zh_TW'), session('url.intended'));

        $url = URL::temporarySignedRoute('member.verification.verify', now()->addMinutes(60), [
            'id' => $member->id,
            'hash' => sha1($member->email),
        ]);

        $this->get($url)->assertRedirect($task->completeUrl('zh_TW'));
    }
}

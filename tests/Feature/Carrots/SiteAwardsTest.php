<?php

namespace Tests\Feature\Carrots;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Models\Message;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteAwardsTest extends TestCase
{
    use RefreshDatabase;

    private function carrotsOf(Member $member): int
    {
        return $member->fresh()->carrots;
    }

    private function makeQuizType(): void
    {
        PersonalityType::query()->create(['code' => 'SNHG', 'name' => '測試', 'summary' => '摘要', 'description' => '說明']);
    }

    public function test_verifying_an_email_awards_once(): void
    {
        $member = Member::factory()->unverified()->create();

        $member->markEmailAsVerified();

        $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));

        $member->forceFill(['email_verified_at' => now()->addMinute()])->save();
        $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));
    }

    public function test_creating_an_already_verified_member_awards_nothing(): void
    {
        $member = Member::factory()->create();

        $this->assertSame(0, $this->carrotsOf($member));
    }

    public function test_setting_the_first_password_awards_once(): void
    {
        $member = Member::factory()->passwordless()->create();

        $member->forceFill(['password' => 'a-new-password'])->save();

        $this->assertSame(config('carrots.site.set_password'), $this->carrotsOf($member));
    }

    public function test_changing_an_existing_password_awards_nothing(): void
    {
        $member = Member::factory()->create();

        $member->forceFill(['password' => 'another-password'])->save();

        $this->assertSame(0, $this->carrotsOf($member));
    }

    public function test_completing_a_quiz_awards_once(): void
    {
        $this->makeQuizType();
        $member = Member::factory()->create();

        foreach ([1, 2] as $_) {
            QuizAttempt::query()->create([
                'uuid' => (string) Str::uuid(), 'user_id' => $member->id, 'scores' => [],
                'type_code' => 'SNHG', 'completed_at' => now(),
            ]);
        }

        $this->assertSame(config('carrots.site.complete_quiz'), $this->carrotsOf($member));
    }

    public function test_an_anonymous_quiz_awards_nothing(): void
    {
        $this->makeQuizType();
        $member = Member::factory()->create();

        QuizAttempt::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => null, 'scores' => [], 'type_code' => 'SNHG', 'completed_at' => now()]);

        $this->assertSame(0, $this->carrotsOf($member));
    }

    public function test_the_first_accepted_friendship_awards_both_members_once(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertSame(0, $this->carrotsOf($a));
        $this->assertTrue($friendship->acceptBy($b));

        $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($a));
        $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($b));

        $c = Member::factory()->create();
        Friendship::factory()->between($c, $a)->create()->acceptBy($a);

        $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($a));
        $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($c));
    }

    public function test_a_refused_acceptance_awards_nothing(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertFalse($friendship->acceptBy($a));
        $this->assertSame(0, $this->carrotsOf($a));
        $this->assertSame(0, $this->carrotsOf($b));
    }

    public function test_the_first_message_awards_the_sender_only_once(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        Message::sendFrom($a, $b, 'hi');
        Message::sendFrom($a, $b, 'again');

        $this->assertSame(config('carrots.site.first_message'), $this->carrotsOf($a));
        $this->assertSame(0, $this->carrotsOf($b));
    }

    public function test_a_blocked_message_awards_nothing(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->assertNull(Message::sendFrom($a, $b, 'hi'));
        $this->assertSame(0, $this->carrotsOf($a));
    }

    public function test_a_failing_award_does_not_break_the_behaviour(): void
    {
        config(['carrots.site.set_password' => 0]);
        $member = Member::factory()->passwordless()->create();

        $this->assertTrue($member->forceFill(['password' => 'a-new-password'])->save());
        $this->assertTrue($member->fresh()->hasPassword());
        $this->assertSame(0, $this->carrotsOf($member));
    }

    public function test_the_login_link_flow_awards_the_verification(): void
    {
        $member = Member::factory()->unverified()->passwordless()->create();
        $token = MemberLoginLink::issueFor($member);

        $this->get(route('member.login.link.consume', ['token' => $token]))->assertRedirect();

        $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));
    }
}

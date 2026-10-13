<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function sentUrl(Member $member): string
    {
        $url = null;

        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });

        return $url;
    }

    public function test_requesting_a_link_emails_a_one_time_link_to_an_active_member(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login/link', ['email' => 'm@example.com'])
            ->assertRedirect()
            ->assertSessionHas('link_status');

        $this->assertStringStartsWith(url('/login/link/'), $this->sentUrl($member));
        $this->assertSame(1, $member->loginLinks()->count());
    }

    public function test_english_request_emails_an_english_link(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/en/login/link', ['email' => 'm@example.com']);

        $this->assertStringStartsWith(url('/en/login/link/'), $this->sentUrl($member));
    }

    public function test_known_and_unknown_emails_get_the_same_response_and_unknown_gets_no_mail(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'known@example.com']);

        $this->post('/login/link', ['email' => 'known@example.com'])
            ->assertSessionHas('link_status', __('member.login.link_sent'));
        $this->post('/login/link', ['email' => 'ghost@example.com'])
            ->assertSessionHas('link_status', __('member.login.link_sent'));

        Notification::assertSentTimes(MemberLoginLinkNotification::class, 1);
        $this->assertDatabaseCount('member_login_links', 1);
    }

    public function test_inactive_members_get_no_link(): void
    {
        Notification::fake();
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login/link', ['email' => 'off@example.com'])->assertSessionHas('link_status');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'alice@example.com']);

        $this->post('/login/link', ['email' => 'Alice@Example.COM']);

        $this->assertNotEmpty($this->sentUrl($member));
    }

    public function test_the_emailed_link_logs_the_member_in_once(): void
    {
        $member = Member::factory()->create();
        $token = MemberLoginLink::issueFor($member);

        $this->get('/login/link/'.$token)->assertRedirect('/member');
        $this->assertAuthenticatedAs($member, 'member');

        auth('member')->logout();
        $this->get('/login/link/'.$token)->assertRedirect('/login')->assertSessionHasErrors('link');
        $this->assertGuest('member');
    }

    public function test_english_link_redirects_to_the_english_center(): void
    {
        $member = Member::factory()->create();

        $this->get('/en/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/en/member');
    }

    public function test_using_a_link_verifies_an_unverified_email(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member));

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_invalid_expired_revoked_and_inactive_links_do_not_log_in(): void
    {
        $this->get('/login/link/not-a-real-token')->assertRedirect('/login');
        $this->assertGuest('member');

        $expired = Member::factory()->create();
        $expiredToken = MemberLoginLink::issueFor($expired);
        $expired->loginLinks()->update(['expires_at' => now()->subMinute()]);
        $this->get('/login/link/'.$expiredToken)->assertRedirect('/login');

        $inactive = Member::factory()->inactive()->create();
        $inactiveToken = MemberLoginLink::issueFor($inactive);
        $this->get('/login/link/'.$inactiveToken)->assertRedirect('/login');

        $this->assertGuest('member');
    }

    public function test_a_newer_link_invalidates_the_older_one(): void
    {
        $member = Member::factory()->create();
        $old = MemberLoginLink::issueFor($member);
        MemberLoginLink::issueFor($member);

        $this->get('/login/link/'.$old)->assertRedirect('/login');
        $this->assertGuest('member');
    }

    public function test_more_than_five_requests_per_hour_per_email_return_429(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'm@example.com']);
        RateLimiter::clear(MemberLoginLink::throttleKey('m@example.com'));

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post('/login/link', ['email' => 'm@example.com'])
                ->assertSessionHas('link_status');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/login/link', ['email' => 'M@example.com'])
            ->assertStatus(429);
    }

    public function test_unknown_emails_are_throttled_the_same_way(): void
    {
        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$i])->post('/login/link', ['email' => 'ghost@example.com']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])
            ->post('/login/link', ['email' => 'ghost@example.com'])
            ->assertStatus(429);
    }

    public function test_request_requires_a_valid_email(): void
    {
        $this->post('/login/link', ['email' => 'nope'])->assertSessionHasErrors('email');
    }
}

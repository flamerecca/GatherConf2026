<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordlessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function register(array $overrides = [], string $path = '/register'): TestResponse
    {
        return $this->post($path, array_merge([
            'name' => '王小明',
            'email' => 'new@example.com',
            'organization' => 'Kotlin Taiwan',
        ], $overrides));
    }

    public function test_the_form_has_no_password_fields(): void
    {
        $this->get('/register')->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false)
            ->assertSee('name="email"', false);
    }

    public function test_registering_creates_an_unverified_member_without_a_password_and_without_a_session(): void
    {
        Notification::fake();

        $this->register()->assertRedirect('/register/sent');

        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertNull($member->password);
        $this->assertNull($member->email_verified_at);
        $this->assertTrue($member->is_active);
        $this->assertSame('Kotlin Taiwan', $member->organization);
        $this->assertGuest('member');
        Notification::assertSentTo($member, MemberLoginLinkNotification::class);
    }

    public function test_a_submitted_password_is_ignored(): void
    {
        Notification::fake();

        $this->register(['password' => 'attacker-chosen', 'password_confirmation' => 'attacker-chosen']);

        $this->assertNull(Member::query()->where('email', 'new@example.com')->firstOrFail()->password);
    }

    public function test_the_emailed_link_logs_in_and_verifies_the_new_member(): void
    {
        Notification::fake();
        $this->register();
        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $this->assertAuthenticatedAs($member, 'member');
        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_registering_an_existing_email_does_not_overwrite_it_and_sends_a_login_link(): void
    {
        Notification::fake();
        $existing = Member::factory()->create(['email' => 'new@example.com', 'name' => '原本的名字', 'organization' => '原本社群']);
        $hash = $existing->password;

        $this->register(['name' => '攻擊者', 'organization' => '攻擊社群'])->assertRedirect('/register/sent');

        $existing->refresh();
        $this->assertSame('原本的名字', $existing->name);
        $this->assertSame('原本社群', $existing->organization);
        $this->assertSame($hash, $existing->password);
        $this->assertSame(1, Member::query()->count());
        Notification::assertSentTo($existing, MemberLoginLinkNotification::class);
    }

    public function test_responses_are_identical_for_new_existing_and_deactivated_emails(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'existing@example.com']);
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $responses = collect(['fresh@example.com', 'existing@example.com', 'off@example.com'])
            ->map(fn (string $email) => $this->register(['email' => $email]));

        $responses->each->assertRedirect('/register/sent');
        $this->assertCount(1, $responses->map(fn ($response) => $response->getStatusCode())->unique());
    }

    public function test_a_deactivated_member_gets_no_email_and_is_not_changed(): void
    {
        Notification::fake();
        $off = Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->register(['email' => 'off@example.com']);

        Notification::assertNothingSent();
        $this->assertFalse($off->fresh()->is_active);
        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_the_email_is_normalized(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'alice@example.com']);

        $this->register(['email' => '  Alice@Example.COM ']);

        $this->assertSame(1, Member::query()->count());
    }

    public function test_validation_rejects_missing_name_and_bad_email(): void
    {
        $this->register(['name' => ''])->assertSessionHasErrors('name');
        $this->register(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('members', 0);
    }

    public function test_organization_is_optional(): void
    {
        Notification::fake();

        $this->register(['organization' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Member::query()->firstOrFail()->organization);
    }

    public function test_the_hourly_email_quota_is_shared_with_the_login_link_request(): void
    {
        Notification::fake();
        RateLimiter::clear(MemberLoginLink::throttleKey('shared@example.com'));

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT - 1; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.$i])
                ->post('/login/link', ['email' => 'shared@example.com']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.2.1.1'])->register(['email' => 'shared@example.com'])->assertRedirect('/register/sent');
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.1.2'])->register(['email' => 'shared@example.com'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->register(['email' => "u{$i}@example.com"]);
        }

        $this->register(['email' => 'u6@example.com'])->assertStatus(429);
    }

    public function test_the_sent_page_renders_in_both_languages_for_guests_only(): void
    {
        $this->get('/register/sent')->assertOk()->assertSee('登入連結已經寄出')->assertSee('noindex', false);
        $this->get('/en/register/sent')->assertOk()->assertSee('login link has been sent');

        $this->actingAs(Member::factory()->create(), 'member')->get('/register/sent')->assertRedirect('/member');
    }

    public function test_english_registration_sends_an_english_link_and_redirects_to_the_english_page(): void
    {
        Notification::fake();

        $this->register([], '/en/register')->assertRedirect('/en/register/sent');

        $member = Member::query()->firstOrFail();
        $url = null;
        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        $this->assertStringContainsString('/en/login/link/', $url);
    }

    public function test_pre_hijacking_an_email_leaves_the_attacker_with_nothing(): void
    {
        Notification::fake();
        $this->register(['email' => 'victim@example.com', 'password' => 'attacker-pass', 'password_confirmation' => 'attacker-pass']);
        $member = Member::query()->where('email', 'victim@example.com')->firstOrFail();

        $this->assertGuest('member');
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'attacker-pass'])->assertSessionHasErrors('email');

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');
        auth('member')->logout();
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'attacker-pass'])->assertSessionHasErrors('email');
    }
}

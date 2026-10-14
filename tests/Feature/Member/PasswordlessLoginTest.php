<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\ResetMemberPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_link_login_records_the_time_in_the_session(): void
    {
        $this->freezeTime();
        $member = Member::factory()->passwordless()->unverified()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $this->assertSame(now()->timestamp, session('member_link_login_at'));
    }

    public function test_a_password_login_does_not_record_it(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'password']);

        $this->assertNull(session('member_link_login_at'));
    }

    public function test_the_login_page_explains_the_link_option_in_both_languages(): void
    {
        $this->get('/login')->assertOk()->assertSee('還沒有設定密碼');
        $this->get('/en/login')->assertOk()->assertSee('Have not set a password')->assertDontSee('還沒有設定密碼');
    }

    public function test_resetting_the_password_of_a_passwordless_member_sets_it_and_verifies_the_email(): void
    {
        $member = Member::factory()->passwordless()->unverified()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/login');

        $member->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $member->password));
        $this->assertNotNull($member->email_verified_at);
    }

    public function test_a_deactivated_member_can_reset_but_still_cannot_log_in(): void
    {
        $member = Member::factory()->passwordless()->inactive()->create(['email' => 'off@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'off@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'brand-new-pass'])
            ->assertSessionHasErrors(['email' => __('member.errors.inactive')]);
        $this->assertGuest('member');
    }

    public function test_forgot_password_works_for_passwordless_members(): void
    {
        Notification::fake();
        $member = Member::factory()->passwordless()->create(['email' => 'm@example.com']);

        $this->post('/forgot-password', ['email' => 'm@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($member, ResetMemberPasswordNotification::class);
    }
}

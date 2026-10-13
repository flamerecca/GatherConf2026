<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use App\Notifications\ResetMemberPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(string $token, string $password = 'brand-new-pass'): array
    {
        return [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_pages_render_in_both_languages(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('忘記密碼')->assertSee('noindex', false);
        $this->get('/en/forgot-password')->assertOk()->assertSee('Forgot your password');
        $this->get('/reset-password/tok?email=a@example.com')->assertOk()->assertSee('重設密碼');
        $this->get('/en/reset-password/tok?email=a@example.com')->assertOk()->assertSee('Reset your password');
    }

    public function test_requesting_a_reset_emails_a_registered_member(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/forgot-password', ['email' => 'm@example.com'])->assertSessionHas('status', __('member.forgot.sent'));

        Notification::assertSentTo($member, ResetMemberPasswordNotification::class);
    }

    public function test_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ghost@example.com'])->assertSessionHas('status', __('member.forgot.sent'));

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', $this->resetPayload($token))
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('member.reset.done'));

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_english_reset_redirects_to_the_english_login(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/en/reset-password', $this->resetPayload($token))->assertRedirect('/en/login');
    }

    public function test_an_invalid_token_does_not_change_the_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $original = $member->password;

        $this->post('/reset-password', $this->resetPayload('wrong'))->assertSessionHasErrors('email');

        $this->assertSame($original, $member->fresh()->password);
    }

    public function test_a_token_cannot_be_reused(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', $this->resetPayload($token))->assertRedirect('/login');
        $this->post('/reset-password', $this->resetPayload($token, 'another-pass'))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_a_token_for_an_admin_user_does_not_work_for_a_member_with_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'm@example.com']);
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $userToken = Password::broker('users')->createToken($user);

        $this->post('/reset-password', $this->resetPayload($userToken))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $member->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', $this->resetPayload($token, 'short'))->assertSessionHasErrors('password');
    }
}

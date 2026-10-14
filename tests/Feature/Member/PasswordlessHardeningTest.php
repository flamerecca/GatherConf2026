<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordlessHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
    }

    private function plantSession(Member $member, string $id = 'attacker-session'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $member->id,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'attacker',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_a_link_login_discards_a_leftover_password_and_sessions_of_an_unverified_member(): void
    {
        $member = Member::factory()->unverified()->create();
        $this->plantSession($member);
        $remember = $member->remember_token;

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $member->refresh();
        $this->assertNull($member->password);
        $this->assertNotSame($remember, $member->remember_token);
        $this->assertNotNull($member->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session']);
    }

    public function test_a_link_login_keeps_the_password_of_an_already_verified_member(): void
    {
        $member = Member::factory()->create();
        $hash = $member->password;
        $this->plantSession($member, 'other-device');

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $this->assertSame($hash, $member->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => 'other-device']);
    }

    public function test_the_email_verification_link_discards_leftover_credentials_too(): void
    {
        $member = Member::factory()->unverified()->create();
        $this->plantSession($member);

        $url = URL::temporarySignedRoute('member.verification.verify', now()->addHour(), [
            'id' => $member->id,
            'hash' => sha1($member->email),
        ]);
        $this->get($url)->assertRedirect();

        $member->refresh();
        $this->assertNull($member->password);
        $this->assertNotNull($member->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session']);
    }

    public function test_a_password_reset_ends_leftover_sessions_of_an_unverified_member(): void
    {
        $member = Member::factory()->unverified()->create(['email' => 'v@example.com']);
        $this->plantSession($member);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'v@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session']);
    }

    public function test_the_link_login_waiver_belongs_to_the_member_who_logged_in_by_link(): void
    {
        $this->freezeTime();
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->actingAs($b, 'member')
            ->withSession(['member_link_login' => ['member_id' => $a->id, 'at' => now()->timestamp]])
            ->put('/member/password', ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_the_waiver_is_recorded_with_the_member_id_and_cleared_on_logout_and_password_login(): void
    {
        $this->freezeTime();
        $a = Member::factory()->passwordless()->unverified()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($a));
        $this->assertSame(['member_id' => $a->id, 'at' => now()->timestamp], session('member_link_login'));

        $this->post('/logout');
        $this->assertNull(session('member_link_login'));

        $b = Member::factory()->create(['email' => 'b@example.com']);
        $this->withSession(['member_link_login' => ['member_id' => $a->id, 'at' => now()->timestamp]])
            ->post('/login', ['email' => 'b@example.com', 'password' => 'password']);
        $this->assertNull(session('member_link_login'));
    }

    public function test_changing_the_password_rotates_the_remember_token(): void
    {
        $member = Member::factory()->create();
        $before = $member->remember_token;

        $this->actingAs($member, 'member')->put('/member/password', [
            'current_password' => 'password',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($before, $member->fresh()->remember_token);
    }
}

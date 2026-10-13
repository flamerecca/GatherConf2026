<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MemberAuthConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_guard_authenticates_members_only(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->assertTrue(Auth::guard('member')->attempt(['email' => 'm@example.com', 'password' => 'password']));
        $this->assertTrue(Auth::guard('member')->user()->is($member));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_member_guard_does_not_authenticate_admin_users(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->assertFalse(Auth::guard('member')->attempt(['email' => 'admin@example.com', 'password' => 'password']));
    }

    public function test_member_password_broker_uses_its_own_token_table(): void
    {
        $this->assertSame('member_password_reset_tokens', config('auth.passwords.members.table'));
        $this->assertSame('members', config('auth.passwords.members.provider'));

        $member = Member::factory()->create();

        Password::broker('members')->createToken($member);

        $this->assertDatabaseHas('member_password_reset_tokens', ['email' => $member->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $member->email]);
    }

    public function test_a_logged_in_member_cannot_reach_the_admin_panel(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')
            ->get('/admin')
            ->assertRedirect();
    }
}

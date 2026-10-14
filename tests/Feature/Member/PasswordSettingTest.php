<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function body(array $overrides = []): array
    {
        return array_merge(['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'], $overrides);
    }

    public function test_a_passwordless_member_sees_the_form_without_a_current_password_field(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/member/password')
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertDontSee('name="current_password"', false)
            ->assertSee('noindex', false);
    }

    public function test_a_passwordless_member_can_set_a_password_and_then_log_in_with_it(): void
    {
        $member = Member::factory()->passwordless()->create(['email' => 'm@example.com']);

        $this->actingAs($member, 'member')->put('/member/password', $this->body())
            ->assertRedirect('/member/password')
            ->assertSessionHas('status', __('member.password.saved'));

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));

        auth('member')->logout();
        $this->post('/login', ['email' => 'm@example.com', 'password' => 'brand-new-pass'])->assertRedirect('/member');
    }

    public function test_a_member_with_a_password_must_enter_the_current_one(): void
    {
        $member = Member::factory()->create();
        $original = $member->password;

        $this->actingAs($member, 'member')->get('/member/password')->assertSee('name="current_password"', false);

        $this->actingAs($member, 'member')->put('/member/password', $this->body())
            ->assertSessionHasErrors('current_password');
        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame($original, $member->fresh()->password);

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'password']))
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_a_recent_link_login_waives_the_current_password(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')
            ->withSession(['member_link_login' => ['member_id' => $member->id, 'at' => now()->subMinutes(10)->timestamp]])
            ->put('/member/password', $this->body())
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_an_old_link_login_does_not_waive_the_current_password(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();
        $original = $member->password;

        $this->actingAs($member, 'member')
            ->withSession(['member_link_login' => ['member_id' => $member->id, 'at' => now()->subMinutes(16)->timestamp]])
            ->put('/member/password', $this->body())
            ->assertSessionHasErrors('current_password');

        $this->assertSame($original, $member->fresh()->password);
    }

    public function test_the_new_password_is_validated(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->actingAs($member, 'member')->put('/member/password', $this->body(['password_confirmation' => 'different-pass']))
            ->assertSessionHasErrors('password');
        $this->actingAs($member, 'member')->put('/member/password', ['password' => ['x'], 'password_confirmation' => ['x']])
            ->assertSessionHasErrors('password');

        $this->assertNull($member->fresh()->password);
    }

    public function test_guests_unverified_and_deactivated_members_cannot_use_it(): void
    {
        $this->get('/member/password')->assertRedirect('/login');
        $this->put('/member/password', $this->body())->assertRedirect('/login');

        $this->actingAs(Member::factory()->passwordless()->unverified()->create(), 'member')
            ->get('/member/password')->assertRedirect('/email/verify');

        $this->actingAs(Member::factory()->passwordless()->inactive()->create(), 'member')
            ->get('/member/password')->assertRedirect('/login');
    }

    public function test_english_page_and_redirect(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/en/member/password')->assertOk()->assertSee('Set a password');
        $this->actingAs($member, 'member')->put('/en/member/password', $this->body())->assertRedirect('/en/member/password');
    }

    public function test_changing_the_password_is_rate_limited(): void
    {
        $member = Member::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']));
        }

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']))->assertStatus(429);
    }
}

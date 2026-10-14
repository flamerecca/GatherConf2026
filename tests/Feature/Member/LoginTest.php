<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_pages_render_in_both_languages(): void
    {
        $this->get('/login')->assertOk()->assertSee('會員登入')->assertSee('寄送登入連結');
        $this->get('/en/login')->assertOk()->assertSee('Log in')->assertSee('Send login link')->assertDontSee('會員登入');
    }

    public function test_the_register_button_is_at_the_top_before_both_login_forms(): void
    {
        $page = $this->get('/login')->assertOk()->getContent();

        $registerUrl = route('member.register');
        $button = strpos($page, 'href="'.$registerUrl.'"');
        $passwordForm = strpos($page, '<form method="POST" action="'.route('member.login.store').'"');
        $linkForm = strpos($page, '<form method="POST" action="'.route('member.login.link.request').'"');

        $this->assertNotFalse($button);
        $this->assertLessThan($passwordForm, $button);
        $this->assertLessThan($linkForm, $button);
        $this->assertSame(1, substr_count($page, 'href="'.$registerUrl.'"'));
        $this->assertStringContainsString('rounded-full bg-moss', substr($page, $button, 240));
    }

    public function test_the_prompt_comes_before_the_register_button_in_both_languages(): void
    {
        $this->get('/login')->assertOk()
            ->assertSeeInOrder(['還沒有帳號？', 'href="'.route('member.register').'"'], false);

        $this->get('/en/login')->assertOk()
            ->assertSeeInOrder(['No account yet?', 'href="'.route('member.register.en').'"'], false);
    }

    public function test_the_english_register_button_points_to_the_english_page(): void
    {
        $this->get('/en/login')->assertOk()
            ->assertSee('href="'.route('member.register.en').'"', false)
            ->assertSee('Sign up')
            ->assertDontSee('註冊會員');
    }

    public function test_member_can_log_in_with_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'password'])
            ->assertRedirect('/member');

        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        Member::factory()->create(['email' => 'alice@example.com']);

        $this->post('/login', ['email' => 'Alice@Example.com', 'password' => 'password'])->assertRedirect('/member');
    }

    public function test_wrong_password_and_unknown_email_show_the_same_message(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $wrong = $this->post('/login', ['email' => 'm@example.com', 'password' => 'nope']);
        $unknown = $this->post('/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

        $wrong->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        $unknown->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        $this->assertGuest('member');
    }

    public function test_an_inactive_member_cannot_log_in_even_with_the_right_password(): void
    {
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('member.errors.inactive')]);

        $this->assertGuest('member');
    }

    public function test_an_inactive_member_is_not_told_so_with_a_wrong_password(): void
    {
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
    }

    public function test_english_login_redirects_to_the_english_center(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/en/login', ['email' => 'm@example.com', 'password' => 'password'])->assertRedirect('/en/member');
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'm@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest('member');
    }

    public function test_center_shows_member_details_to_verified_members(): void
    {
        $member = Member::factory()->create(['name' => '王小明', 'organization' => null]);

        $this->actingAs($member, 'member')->get('/member')
            ->assertOk()
            ->assertSee('王小明')
            ->assertSee($member->email)
            ->assertSee('未填寫');
    }

    public function test_center_sends_unverified_members_to_the_verification_notice(): void
    {
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/member')
            ->assertRedirect('/email/verify');

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/en/member')
            ->assertRedirect('/en/email/verify');
    }

    public function test_center_requires_login(): void
    {
        $this->get('/member')->assertRedirect('/login');
        $this->get('/en/member')->assertRedirect('/en/login');
    }
}

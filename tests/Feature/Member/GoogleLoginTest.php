<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\QuizAttempt;
use Database\Seeders\PersonalityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'secret']);
    }

    private function googleReturns(string $email, bool $verified = true, string $name = 'Gina Google'): void
    {
        $user = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => '1', 'name' => $name, 'email' => $email]);
        $user->user = ['email_verified' => $verified];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_the_google_button_shows_on_login_and_register_only_when_configured(): void
    {
        $this->get(route('member.login'))->assertOk()->assertSee('data-google-button', false)->assertSee('使用 Google 登入');
        $this->get(route('member.register'))->assertOk()->assertSee('使用 Google 註冊');
        $this->get(route('member.login.en'))->assertOk()->assertSee('Continue with Google');

        config(['services.google.client_id' => null]);

        $this->get(route('member.login'))->assertOk()->assertDontSee('data-google-button', false);
        $this->get(route('member.google.redirect'))->assertNotFound();
    }

    public function test_the_redirect_goes_to_google(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->with(route('member.google.callback'))->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('member.google.redirect'))->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_a_new_google_user_becomes_a_verified_member_and_is_logged_in(): void
    {
        $this->googleReturns('Gina@Example.com');

        $this->get(route('member.google.callback'))->assertRedirect(route('member.center'));

        $member = Member::query()->sole();
        $this->assertSame('gina@example.com', $member->email);
        $this->assertSame('Gina Google', $member->name);
        $this->assertNotNull($member->email_verified_at);
        $this->assertFalse($member->hasPassword());
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_an_existing_member_is_logged_in_without_a_duplicate(): void
    {
        $member = Member::factory()->create(['email' => 'gina@example.com']);
        $this->googleReturns('gina@example.com');

        $this->get(route('member.google.callback'))->assertRedirect(route('member.center'));

        $this->assertSame(1, Member::count());
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_an_unverified_member_loses_old_credentials_once_google_proves_the_mailbox(): void
    {
        $member = Member::factory()->unverified()->create(['email' => 'gina@example.com', 'password' => 'planted-password']);
        $this->googleReturns('gina@example.com');

        $this->get(route('member.google.callback'))->assertRedirect(route('member.center'));

        $member->refresh();
        $this->assertNotNull($member->email_verified_at);
        $this->assertFalse($member->hasPassword());
    }

    public function test_a_deactivated_member_cannot_log_in_with_google(): void
    {
        Member::factory()->create(['email' => 'gina@example.com', 'is_active' => false]);
        $this->googleReturns('gina@example.com');

        $this->get(route('member.google.callback'))->assertRedirect(route('member.login'))->assertSessionHasErrors('google');

        $this->assertGuest('member');
    }

    public function test_an_unverified_google_email_is_rejected(): void
    {
        $this->googleReturns('gina@example.com', verified: false);

        $this->get(route('member.google.callback'))->assertRedirect(route('member.login'))->assertSessionHasErrors('google');

        $this->assertSame(0, Member::count());
        $this->assertGuest('member');
    }

    public function test_a_failed_google_exchange_returns_to_login_with_an_error(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('member.google.callback'))->assertRedirect(route('member.login'))->assertSessionHasErrors('google');
    }

    public function test_the_quiz_attempt_from_the_register_page_is_attached_to_the_new_member(): void
    {
        $this->seed(PersonalityTypeSeeder::class);
        $attempt = QuizAttempt::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => null, 'scores' => [], 'type_code' => 'NVAD', 'completed_at' => now()]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
        $user = (new GoogleUser)->map(['id' => '1', 'name' => 'Gina', 'email' => 'gina@example.com']);
        $user->user = ['email_verified' => true];
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('member.google.redirect', ['attempt' => $attempt->uuid]));
        $this->get(route('member.google.callback'))->assertRedirect();

        $this->assertSame(Member::query()->sole()->id, $attempt->fresh()->user_id);
    }

    public function test_the_english_login_returns_to_the_english_member_center(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
        $user = (new GoogleUser)->map(['id' => '1', 'name' => 'Gina', 'email' => 'gina@example.com']);
        $user->user = ['email_verified' => true];
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('member.google.redirect.en'));
        $this->get(route('member.google.callback'))->assertRedirect(route('member.center.en'));
    }
}

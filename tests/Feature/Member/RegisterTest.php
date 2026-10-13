<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '王小明',
            'email' => 'new@example.com',
            'organization' => 'Kotlin Taiwan',
            'password' => 'a-strong-pass',
            'password_confirmation' => 'a-strong-pass',
        ], $overrides);
    }

    public function test_registration_pages_render_in_both_languages(): void
    {
        $this->get('/register')->assertOk()->assertSee('註冊會員')->assertSee('noindex', false);
        $this->get('/en/register')->assertOk()->assertSee('Sign up')->assertDontSee('註冊會員');
    }

    public function test_registration_creates_an_active_unverified_member_and_logs_in(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload())->assertRedirect('/email/verify');

        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertTrue($member->is_active);
        $this->assertNull($member->email_verified_at);
        $this->assertSame('Kotlin Taiwan', $member->organization);
        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);
    }

    public function test_english_registration_redirects_to_the_english_notice(): void
    {
        Notification::fake();

        $this->post('/en/register', $this->payload())->assertRedirect('/en/email/verify');
    }

    public function test_email_is_normalized_to_lower_case(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload(['email' => 'Alice@Example.COM']));

        $this->assertDatabaseHas('members', ['email' => 'alice@example.com']);
    }

    public function test_duplicate_email_is_rejected_regardless_of_case(): void
    {
        Member::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->payload(['email' => 'Taken@Example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Member::query()->count());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->post('/register', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/register', $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'different-pass']))->assertSessionHasErrors('password');
        $this->assertSame(0, Member::query()->count());
    }

    public function test_organization_is_optional(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload(['organization' => '']))->assertSessionHasNoErrors();

        $this->assertNull(Member::query()->firstOrFail()->organization);
    }

    public function test_a_logged_in_member_cannot_open_the_registration_page(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/register')->assertRedirect('/member');
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->payload(['email' => "u{$i}@example.com"]));
        }

        $this->post('/register', $this->payload(['email' => 'u6@example.com']))->assertStatus(429);
    }
}

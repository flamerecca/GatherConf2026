<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function signedUrl(Member $member, string $name = 'member.verification.verify', ?string $hash = null): string
    {
        return URL::temporarySignedRoute($name, now()->addMinutes(60), [
            'id' => $member->id,
            'hash' => $hash ?? sha1($member->email),
        ]);
    }

    public function test_notice_page_renders_for_unverified_members(): void
    {
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/email/verify')
            ->assertOk()
            ->assertSee('請驗證你的 Email')
            ->assertSee('noindex', false);

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/en/email/verify')
            ->assertOk()
            ->assertSee('Please verify your email');
    }

    public function test_notice_page_requires_login(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
    }

    public function test_verified_members_are_sent_on_to_the_center(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/email/verify')->assertRedirect('/member');
    }

    public function test_a_valid_signed_link_verifies_the_email_even_when_logged_out(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member))->assertRedirect('/login');

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_a_logged_in_member_is_sent_to_the_center_after_verifying(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->get($this->signedUrl($member))->assertRedirect('/member');
    }

    public function test_english_link_redirects_to_english_pages(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member, 'member.verification.verify.en'))->assertRedirect('/en/login');
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member).'x')->assertForbidden();

        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_a_wrong_hash_is_rejected(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member, hash: sha1('someone-else@example.com')))->assertForbidden();

        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_an_unknown_member_returns_404(): void
    {
        $url = URL::temporarySignedRoute('member.verification.verify', now()->addMinutes(60), ['id' => 999999, 'hash' => 'x']);

        $this->get($url)->assertNotFound();
    }

    public function test_verifying_twice_is_harmless(): void
    {
        $member = Member::factory()->unverified()->create();
        $url = $this->signedUrl($member);

        $this->get($url);
        $first = $member->fresh()->email_verified_at;
        $this->get($url)->assertRedirect();

        $this->assertEquals($first, $member->fresh()->email_verified_at);
    }

    public function test_resend_sends_the_notification_and_is_rate_limited(): void
    {
        Notification::fake();
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->post('/email/verification-notification')->assertSessionHas('status');
        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/email/verification-notification');
        }

        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_resend_is_a_no_op_for_verified_members(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->post('/email/verification-notification')->assertRedirect('/member');

        Notification::assertNothingSent();
    }
}

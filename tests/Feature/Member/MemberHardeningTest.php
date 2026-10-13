<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MemberHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function breakMailer(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException('smtp down');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });

        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);
    }

    public function test_member_logout_keeps_the_admin_session_in_the_same_browser(): void
    {
        $admin = User::factory()->create();
        $member = Member::factory()->create();
        $webKey = Auth::guard('web')->getName();

        $this->withSession([$webKey => $admin->id, Auth::guard('member')->getName() => $member->id])
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertTrue(session()->has($webKey));
        $this->assertSame($admin->id, session($webKey));
    }

    public function test_deactivating_a_member_does_not_log_out_an_admin_in_the_same_browser(): void
    {
        $admin = User::factory()->create();
        $member = Member::factory()->inactive()->create();
        $webKey = Auth::guard('web')->getName();

        $this->withSession([$webKey => $admin->id, Auth::guard('member')->getName() => $member->id])
            ->get('/member')
            ->assertRedirect('/login');

        $this->assertSame($admin->id, session($webKey));
    }

    public function test_public_forms_do_not_share_one_rate_limit_bucket(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'U',
                'email' => "u{$i}@example.com",
                'password' => 'a-strong-pass',
                'password_confirmation' => 'a-strong-pass',
            ]);
        }

        $this->post('/login/link', ['email' => 'someone@example.com'])->assertStatus(302);
        $this->post('/forgot-password', ['email' => 'someone@example.com'])->assertStatus(302);
    }

    public function test_registration_survives_a_mail_failure(): void
    {
        $this->breakMailer();

        $this->post('/register', [
            'name' => '王小明',
            'email' => 'new@example.com',
            'password' => 'a-strong-pass',
            'password_confirmation' => 'a-strong-pass',
        ])->assertRedirect('/email/verify');

        $this->assertDatabaseHas('members', ['email' => 'new@example.com']);
        $this->assertAuthenticated('member');
    }

    public function test_link_request_gives_the_same_response_when_mail_fails(): void
    {
        $this->breakMailer();
        Member::factory()->create(['email' => 'known@example.com']);

        $this->post('/login/link', ['email' => 'known@example.com'])
            ->assertRedirect()
            ->assertSessionHas('link_status', __('member.login.link_sent'));

        $this->flushSession();

        $this->post('/login/link', ['email' => 'ghost@example.com'])
            ->assertRedirect()
            ->assertSessionHas('link_status', __('member.login.link_sent'));
    }

    public function test_forgot_password_gives_the_same_response_when_mail_fails(): void
    {
        $this->breakMailer();
        Member::factory()->create(['email' => 'known@example.com']);

        $this->post('/forgot-password', ['email' => 'known@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', __('member.forgot.sent'));
    }

    public function test_admin_action_reports_a_mail_failure(): void
    {
        $this->breakMailer();
        $this->actingAs(User::factory()->create());
        $member = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member))
            ->assertNotified('寄送失敗');
    }
}

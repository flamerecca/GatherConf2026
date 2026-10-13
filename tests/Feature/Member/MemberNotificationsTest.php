<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\MemberLoginLinkNotification;
use App\Notifications\ResetMemberPasswordNotification;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'member.verification.verify' => '/email/verify/{id}/{hash}',
            'member.password.reset' => '/reset-password/{token}',
        ] as $name => $path) {
            Route::get($path, fn () => 'ok')->name($name);
            Route::get('/en'.$path, fn () => 'ok')->name($name.'.en');
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_login_link_mail_uses_the_given_url_and_locale(): void
    {
        $member = Member::factory()->create();

        $zh = (new MemberLoginLinkNotification('https://example.test/login/link/abc'))->locale('zh_TW');
        $en = (new MemberLoginLinkNotification('https://example.test/en/login/link/abc'))->locale('en');

        app()->setLocale('zh_TW');
        $this->assertSame('你的 GatherConf 登入連結', $zh->toMail($member)->subject);
        app()->setLocale('en');
        $this->assertSame('Your GatherConf login link', $en->toMail($member)->subject);
        $this->assertSame('https://example.test/login/link/abc', $zh->toMail($member)->actionUrl);
    }

    public function test_verification_mail_uses_a_signed_route_for_the_current_locale(): void
    {
        $member = Member::factory()->unverified()->create();

        app()->setLocale('en');
        $notification = new VerifyMemberEmailNotification;
        $url = $notification->toMail($member)->actionUrl;

        $this->assertStringContainsString('/en/email/verify/'.$member->id.'/'.sha1($member->email), $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame('Verify your email', $notification->toMail($member)->subject);
    }

    public function test_reset_mail_contains_token_and_email_for_the_current_locale(): void
    {
        $member = Member::factory()->create();

        app()->setLocale('zh_TW');
        $url = (new ResetMemberPasswordNotification('tok123'))->toMail($member)->actionUrl;

        $this->assertStringContainsString('/reset-password/tok123', $url);
        $this->assertStringNotContainsString('/en/', $url);
        $this->assertStringContainsString('email='.urlencode($member->email), $url);
    }

    public function test_member_sends_the_custom_notifications(): void
    {
        Notification::fake();
        $member = Member::factory()->unverified()->create();

        $member->sendEmailVerificationNotification();
        $member->sendPasswordResetNotification('tok');

        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);
        Notification::assertSentTo($member, ResetMemberPasswordNotification::class);
    }
}

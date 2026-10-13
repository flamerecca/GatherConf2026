<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Models\User;
use App\Notifications\MemberLoginLinkNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SendLoginLinkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_action_emails_a_link_to_the_member_and_revokes_the_old_one(): void
    {
        Notification::fake();
        $member = Member::factory()->create();
        $oldToken = MemberLoginLink::issueFor($member);

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member))
            ->assertNotified('已寄送登入連結');

        Notification::assertSentTo($member, MemberLoginLinkNotification::class);
        $this->assertNull(MemberLoginLink::consume($oldToken));
        $this->assertSame(2, $member->loginLinks()->count());
    }

    public function test_the_admin_never_sees_the_link(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        $component = Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('sendLoginLink');

        $url = null;
        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        $component->assertDontSee($url);
    }

    public function test_action_is_hidden_for_inactive_members(): void
    {
        $inactive = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->assertActionHidden(TestAction::make('sendLoginLink')->table($inactive));
    }

    public function test_action_respects_the_shared_hourly_limit(): void
    {
        Notification::fake();
        $member = Member::factory()->create();
        $key = MemberLoginLink::throttleKey($member->email);

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            RateLimiter::hit($key, 3600);
        }

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member))
            ->assertNotified('寄送過於頻繁');

        Notification::assertNothingSent();
        $this->assertSame(0, $member->loginLinks()->count());
    }

    public function test_sending_counts_toward_the_front_end_limit(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member));

        $this->assertSame(
            MemberLoginLink::HOURLY_LIMIT - 1,
            RateLimiter::remaining(MemberLoginLink::throttleKey($member->email), MemberLoginLink::HOURLY_LIMIT),
        );
    }
}

<?php

namespace Tests\Feature\CommunityMessages;

use App\Models\CommunityMessage;
use App\Models\Member;
use App\Notifications\CommunityMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CommunityMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('member-community-message');
    }

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge(['subject' => '想詢問合作', 'body' => '請問有沒有講座可以一起辦？', 'agree_conduct' => '1'], $override);
    }

    public function test_the_form_shows_the_community_name_in_both_languages(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->get(route('member.community-message.create', 'gdg-taoyuan'))
            ->assertOk()->assertSee('GDG 桃園')->assertSee('noindex', false);
        $this->asMember($me)->get(route('member.community-message.create.en', 'gdg-taoyuan'))
            ->assertOk()->assertSee(trans('community-messages.create.heading', [], 'en'), false);
    }

    public function test_the_form_links_to_the_code_of_conduct(): void
    {
        $this->asMember(Member::factory()->create())
            ->get(route('member.community-message.create', 'gdg-taoyuan'))
            ->assertSee(route('conduct'), false);
    }

    public function test_a_guest_is_sent_to_login_and_returns_to_the_form(): void
    {
        $url = route('member.community-message.create', 'gdg-taoyuan');

        $this->get($url)->assertRedirect(route('member.login'));
        $this->assertSame($url, session('url.intended'));
    }

    public function test_unverified_and_inactive_members_cannot_send(): void
    {
        $this->asMember(Member::factory()->unverified()->create())
            ->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
            ->assertRedirect(route('member.verification.notice'));
        $this->asMember(Member::factory()->inactive()->create())
            ->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload());

        $this->assertSame(0, CommunityMessage::query()->count());
    }

    public function test_an_unknown_slug_is_a_404_for_get_and_post(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->get(route('member.community-message.create', 'nope'))->assertNotFound();
        $this->asMember($me)->post(route('member.community-message.store', 'nope'), $this->payload())->assertNotFound();
        $this->assertSame(0, CommunityMessage::query()->count());
    }

    public function test_a_message_is_stored_trimmed_with_the_community_name(): void
    {
        Notification::fake();
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload(['subject' => '  想詢問  ']))
            ->assertRedirect(route('member.community-messages'));

        $message = CommunityMessage::query()->firstOrFail();
        $this->assertSame('想詢問', $message->subject);
        $this->assertSame('GDG 桃園', $message->community_name);
        $this->assertSame('gdg-taoyuan', $message->community_slug);
        $this->assertSame('pending', $message->status);
        $this->assertTrue($message->member->is($me));
    }

    public function test_the_stored_name_is_traditional_chinese_even_from_the_english_form(): void
    {
        Notification::fake();
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.community-message.store.en', 'gdg-taoyuan'), $this->payload())
            ->assertRedirect(route('member.community-messages.en'));

        $this->assertSame('GDG 桃園', CommunityMessage::query()->firstOrFail()->community_name);
    }

    public function test_validation(): void
    {
        $me = Member::factory()->create();
        $url = route('member.community-message.store', 'gdg-taoyuan');

        $this->asMember($me)->post($url, $this->payload(['subject' => '']))->assertSessionHasErrors('subject');
        $this->asMember($me)->post($url, $this->payload(['body' => '   ']))->assertSessionHasErrors('body');
        $this->asMember($me)->post($url, $this->payload(['subject' => str_repeat('字', 101)]))->assertSessionHasErrors('subject');
        $this->asMember($me)->post($url, $this->payload(['body' => str_repeat('字', 1001)]))->assertSessionHasErrors('body');
        $this->asMember($me)->post($url, $this->payload(['agree_conduct' => null]))->assertSessionHasErrors('agree_conduct');

        $this->assertSame(0, CommunityMessage::query()->count());
    }

    public function test_the_sixth_message_in_a_day_is_refused(): void
    {
        $me = Member::factory()->create();
        CommunityMessage::factory()->count(5)->for($me)->create();

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
            ->assertSessionHasErrors('body');

        $this->assertSame(5, CommunityMessage::query()->count());
    }

    public function test_older_messages_do_not_count_against_the_limit(): void
    {
        Notification::fake();
        $me = Member::factory()->create();
        CommunityMessage::factory()->count(5)->for($me)->create(['created_at' => now()->subDays(2)]);

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
            ->assertRedirect(route('member.community-messages'));
    }

    public function test_the_request_throttle_returns_429(): void
    {
        $me = Member::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload(['agree_conduct' => null]));
        }

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())->assertStatus(429);
    }

    public function test_a_confirmation_email_is_sent_without_the_content(): void
    {
        Notification::fake();
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload());

        Notification::assertSentTo($me, CommunityMessageReceivedNotification::class, function ($notification) use ($me) {
            $mail = $notification->toMail($me);
            $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);

            return str_contains($text, 'GDG 桃園')
                && ! str_contains($text, '想詢問合作')
                && ! str_contains($text, '講座');
        });
    }

    public function test_a_mail_failure_does_not_break_sending(): void
    {
        $me = Member::factory()->create();
        config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
            ->assertRedirect(route('member.community-messages'));

        $this->assertSame(1, CommunityMessage::query()->count());
    }

    public function test_the_member_list_shows_only_own_messages_without_body_or_note(): void
    {
        $me = Member::factory()->create();
        CommunityMessage::factory()->for($me)->create(['subject' => '我的主旨', 'body' => '我的內文秘密', 'admin_note' => '內部備註', 'status' => 'forwarded']);
        CommunityMessage::factory()->create(['subject' => '別人的主旨']);

        $this->asMember($me)->get(route('member.community-messages'))
            ->assertOk()
            ->assertSee('我的主旨')
            ->assertSee(__('community-messages.status.forwarded'))
            ->assertDontSee('別人的主旨')
            ->assertDontSee('我的內文秘密')
            ->assertDontSee('內部備註');
    }

    public function test_the_list_requires_login_and_has_an_english_page(): void
    {
        $this->get(route('member.community-messages'))->assertRedirect(route('member.login'));
        $this->asMember(Member::factory()->create())->get(route('member.community-messages.en'))
            ->assertOk()
            ->assertSee(trans('community-messages.list.heading', [], 'en'), false);
    }

    public function test_the_member_center_links_to_the_list(): void
    {
        $this->asMember(Member::factory()->create())->get(route('member.center'))
            ->assertSee(route('member.community-messages'), false);
    }
}

<?php

namespace Tests\Feature\Bottles;

use App\Models\DriftBottle;
use App\Models\Member;
use App\Notifications\DriftBottleReceivedNotification;
use App\Notifications\DriftBottleRepliedNotification;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class BottleFlowTest extends TestCase
{
    use RefreshDatabase;

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    public function test_throwing_a_bottle_requires_conduct_agreement(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.bottles.store'), ['body' => '哈囉'])
            ->assertSessionHasErrors('agree_conduct');

        $this->assertSame(0, DriftBottle::query()->count());
    }

    public function test_throwing_validates_length_and_contact_info(): void
    {
        $me = Member::factory()->create();

        foreach ([str_repeat('字', 301), '   ', '看 https://example.com', '寫信 a@b.co'] as $body) {
            $this->asMember($me)->post(route('member.bottles.store'), ['body' => $body, 'agree_conduct' => '1'])
                ->assertSessionHasErrors('body');
        }

        $this->assertSame(0, DriftBottle::query()->count());
    }

    public function test_a_bottle_is_thrown_and_delivered(): void
    {
        Notification::fake();
        $me = Member::factory()->create();
        $stranger = Member::factory()->create();

        $this->asMember($me)->post(route('member.bottles.store'), ['body' => ' 哈囉 ', 'agree_conduct' => '1'])
            ->assertRedirect(route('member.bottles'));

        $bottle = DriftBottle::query()->firstOrFail();
        $this->assertSame('哈囉', $bottle->body);
        $this->assertTrue($bottle->recipient->is($stranger));
        Notification::assertSentTo($stranger, DriftBottleReceivedNotification::class);
    }

    public function test_the_fourth_bottle_in_a_day_is_refused(): void
    {
        $me = Member::factory()->create();
        Member::factory()->create();
        DriftBottle::factory()->count(3)->create(['sender_id' => $me->id]);

        $this->asMember($me)->post(route('member.bottles.store'), ['body' => '哈囉', 'agree_conduct' => '1'])
            ->assertSessionHasErrors('body');

        $this->assertSame(3, DriftBottle::query()->count());
    }

    public function test_picking_a_bottle(): void
    {
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create();

        $this->asMember($me)->post(route('member.bottles.pick'))
            ->assertRedirect(route('member.bottles.show', $bottle));

        $this->assertTrue($bottle->fresh()->recipient->is($me));
    }

    public function test_picking_from_an_empty_sea_does_not_use_a_pick(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.bottles.pick'))
            ->assertRedirect(route('member.bottles'))
            ->assertSessionHas('status');

        $this->assertSame(DriftBottle::DAILY_PICK_LIMIT, DriftBottle::picksLeft($me));
    }

    public function test_picking_is_blocked_when_holding_five_or_after_ten_picks(): void
    {
        $me = Member::factory()->create();
        $author = Member::factory()->inactive()->create();
        DriftBottle::factory()->count(5)->create(['sender_id' => $author->id, 'recipient_id' => $me->id, 'assigned_at' => now()]);
        $inSea = DriftBottle::factory()->create();

        $this->asMember($me)->post(route('member.bottles.pick'))->assertSessionHasErrors('pick');
        $this->assertNull($inSea->fresh()->recipient_id);

        DriftBottle::query()->where('recipient_id', $me->id)->update(['opened_at' => now()]);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit(DriftBottle::pickKey($me), 86400);
        }

        $this->asMember($me)->post(route('member.bottles.pick'))->assertSessionHasErrors('pick');
        $this->assertNull($inSea->fresh()->recipient_id);
    }

    public function test_the_recipient_sees_the_bottle_anonymously_and_opens_it(): void
    {
        $sender = Member::factory()->create(['name' => '寄件者小明', 'email' => 'sender@example.com', 'organization' => '神秘組織']);
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $me->id, 'body' => '嗨陌生人']);

        $this->asMember($me)->get(route('member.bottles.show', $bottle))
            ->assertOk()
            ->assertSee('嗨陌生人')
            ->assertDontSee('寄件者小明')
            ->assertDontSee('sender@example.com')
            ->assertDontSee('神秘組織')
            ->assertDontSee($sender->id);

        $this->assertNotNull($bottle->fresh()->opened_at);
    }

    public function test_the_sender_sees_the_reply_anonymously(): void
    {
        $me = Member::factory()->create();
        $recipient = Member::factory()->create(['name' => '收件者小華', 'email' => 'rcpt@example.com']);
        $bottle = DriftBottle::factory()->create([
            'sender_id' => $me->id, 'recipient_id' => $recipient->id, 'opened_at' => now(),
            'replied_at' => now(), 'reply_body' => '收到你的瓶子了',
        ]);

        $this->asMember($me)->get(route('member.bottles.show', $bottle))
            ->assertOk()
            ->assertSee('收到你的瓶子了')
            ->assertDontSee('收件者小華')
            ->assertDontSee('rcpt@example.com')
            ->assertDontSee($recipient->id);
    }

    public function test_non_participants_get_404(): void
    {
        $bottle = DriftBottle::factory()->create(['recipient_id' => Member::factory()->create()->id]);

        $this->asMember(Member::factory()->create())->get(route('member.bottles.show', $bottle))->assertNotFound();
        $this->asMember(Member::factory()->create())->post(route('member.bottles.reply', $bottle), ['body' => 'x'])->assertNotFound();
        $this->asMember(Member::factory()->create())->post(route('member.bottles.report', $bottle), ['target' => 'bottle'])->assertNotFound();
    }

    public function test_a_removed_bottle_is_hidden_from_the_recipient_but_flagged_for_the_sender(): void
    {
        $sender = Member::factory()->create();
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create([
            'sender_id' => $sender->id, 'recipient_id' => $me->id, 'opened_at' => now(), 'removed_at' => now(), 'body' => '違規內容',
        ]);

        $this->asMember($me)->get(route('member.bottles.show', $bottle))->assertNotFound();
        $this->asMember($sender)->get(route('member.bottles.show', $bottle))
            ->assertOk()
            ->assertSee(__('bottles.show.removed'))
            ->assertDontSee('違規內容');
    }

    public function test_replying_notifies_the_sender_and_only_once(): void
    {
        Notification::fake();
        $sender = Member::factory()->create();
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $me->id, 'opened_at' => now()]);

        $this->asMember($me)->post(route('member.bottles.reply', $bottle), ['body' => '收到'])
            ->assertRedirect(route('member.bottles.show', $bottle));
        $this->asMember($me)->post(route('member.bottles.reply', $bottle), ['body' => '再回'])
            ->assertSessionHasErrors('body');

        $this->assertSame('收到', $bottle->fresh()->reply_body);
        Notification::assertSentToTimes($sender, DriftBottleRepliedNotification::class, 1);
    }

    public function test_reporting_hides_the_bottle(): void
    {
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['recipient_id' => $me->id, 'opened_at' => now()]);

        $this->asMember($me)->post(route('member.bottles.report', $bottle), ['target' => 'bottle', 'reason' => '騷擾'])
            ->assertRedirect(route('member.bottles'));

        $this->assertNotNull($bottle->fresh()->removed_at);
        $this->asMember($me)->get(route('member.bottles.show', $bottle))->assertNotFound();
    }

    public function test_guests_and_unverified_members_are_redirected(): void
    {
        $this->get(route('member.bottles'))->assertRedirect(route('member.login'));
        $this->asMember(Member::factory()->unverified()->create())->get(route('member.bottles'))
            ->assertRedirect(route('member.verification.notice'));
    }

    public function test_english_pages_are_in_english(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->get(route('member.bottles.en'))
            ->assertOk()
            ->assertSee(trans('bottles.index.heading', [], 'en'), false)
            ->assertSee('noindex', false);
    }

    public function test_the_form_links_to_the_code_of_conduct(): void
    {
        $this->asMember(Member::factory()->create())->get(route('member.bottles.create'))
            ->assertOk()
            ->assertSee(route('conduct'), false);
    }

    public function test_the_member_center_links_to_the_bottles(): void
    {
        $this->asMember(Member::factory()->create())->get(route('member.center'))
            ->assertOk()
            ->assertSee(route('member.bottles'), false);
    }

    public function test_an_invalid_or_duplicate_report_is_not_reported_as_sent(): void
    {
        $sender = Member::factory()->create();
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $me->id, 'opened_at' => now()]);

        $this->asMember($sender)->post(route('member.bottles.report', $bottle), ['target' => 'bottle'])
            ->assertSessionHasErrors('target');
        $this->assertNull($bottle->fresh()->removed_at);

        $this->asMember($me)->post(route('member.bottles.report', $bottle), ['target' => 'bottle'])
            ->assertSessionHas('status');
        $this->asMember($me)->post(route('member.bottles.report', $bottle), ['target' => 'bottle'])
            ->assertSessionHasErrors('target');
    }

    public function test_the_pick_quota_is_reserved_and_returned_when_the_sea_is_empty(): void
    {
        $me = Member::factory()->create();

        $this->asMember($me)->post(route('member.bottles.pick'));

        $this->assertSame(DriftBottle::DAILY_PICK_LIMIT, DriftBottle::picksLeft($me));

        DriftBottle::factory()->create();
        $this->asMember($me)->post(route('member.bottles.pick'));

        $this->assertSame(DriftBottle::DAILY_PICK_LIMIT - 1, DriftBottle::picksLeft($me));
    }

    private function lockTimesOut(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->andThrow(new LockTimeoutException);
        Cache::partialMock()->shouldReceive('lock')->andReturn($lock);
    }

    public function test_a_lock_timeout_while_throwing_is_a_validation_error_not_a_500(): void
    {
        $me = Member::factory()->create();
        $this->lockTimesOut();

        $this->asMember($me)->post(route('member.bottles.store'), ['body' => '哈囉', 'agree_conduct' => '1'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, DriftBottle::query()->count());
    }

    public function test_a_lock_timeout_while_picking_is_a_validation_error_not_a_500(): void
    {
        $me = Member::factory()->create();
        $bottle = DriftBottle::factory()->create();
        $this->lockTimesOut();

        $this->asMember($me)->post(route('member.bottles.pick'))
            ->assertSessionHasErrors('pick');

        $this->assertNull($bottle->fresh()->recipient_id);
        $this->assertSame(DriftBottle::DAILY_PICK_LIMIT, DriftBottle::picksLeft($me));
    }
}

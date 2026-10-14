<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MessageConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array{0: Member, 1: Member, 2: Friendship}
     */
    private function friends(): array
    {
        $a = Member::factory()->create(['name' => '甲']);
        $b = Member::factory()->create(['name' => '乙']);
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();

        return [$a, $b, $friendship];
    }

    public function test_conversation_shows_messages_in_order_with_the_privacy_notice(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->create(['body' => '第一則']);
        Message::factory()->from($b, $a)->create(['body' => '第二則']);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)
            ->assertOk()
            ->assertSeeInOrder(['第一則', '第二則'])
            ->assertSee('訊息可能因管理與檢舉處理而由管理者檢視')
            ->assertSee('noindex', false);

        $this->actingAs($a, 'member')->get('/en/messages/'.$friendship->id)
            ->assertOk()
            ->assertSee('Messages may be viewed by administrators')
            ->assertDontSee('訊息可能因管理');
    }

    public function test_opening_the_conversation_marks_only_received_messages_as_read(): void
    {
        [$a, $b, $friendship] = $this->friends();
        $received = Message::factory()->from($b, $a)->create();
        $sent = Message::factory()->from($a, $b)->create();

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertOk();

        $this->assertNotNull($received->fresh()->read_at);
        $this->assertNull($sent->fresh()->read_at);
    }

    public function test_the_sender_sees_a_read_marker_after_the_recipient_opens_it(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->read()->create(['body' => '已被讀']);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertSee('已讀');
    }

    public function test_only_the_latest_page_is_shown_with_a_link_to_older_messages(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();

        $first = Message::latestPage($a, $b)->first();

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)
            ->assertSee('?before='.$first->id, false);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id.'?before='.$first->id)
            ->assertOk()
            ->assertDontSee('?before=', false);
    }

    public function test_non_participants_strangers_and_pending_get_404(): void
    {
        [$a, $b, $friendship] = $this->friends();
        $outsider = Member::factory()->create();
        $pending = Friendship::factory()->between($a, $outsider)->create();

        $this->actingAs($outsider, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($outsider, 'member')->post('/messages/'.$friendship->id, ['body' => 'x'])->assertNotFound();
        $this->actingAs($a, 'member')->get('/messages/'.$pending->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$pending->id, ['body' => 'x'])->assertNotFound();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_or_inactive_friends_get_404_and_nothing_is_written(): void
    {
        [$a, $b, $friendship] = $this->friends();
        MemberBlock::factory()->create(['blocker_id' => $b->id, 'blocked_id' => $a->id]);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])->assertNotFound();
        $this->assertDatabaseCount('messages', 0);

        MemberBlock::query()->delete();
        $b->update(['is_active' => false]);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])->assertNotFound();
    }

    public function test_sending_stores_the_message_and_redirects_back(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => "  你好\n第二行  "])
            ->assertRedirect('/messages/'.$friendship->id);

        $message = Message::query()->firstOrFail();
        $this->assertSame("你好\n第二行", $message->body);
        $this->assertSame($a->id, $message->sender_id);
        $this->assertSame($b->id, $message->recipient_id);
    }

    public function test_english_send_redirects_to_the_english_conversation(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/en/messages/'.$friendship->id, ['body' => 'hi'])
            ->assertRedirect('/en/messages/'.$friendship->id);
    }

    public function test_body_is_validated(): void
    {
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '   '])->assertSessionHasErrors('body');
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => str_repeat('字', 1001)])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_exactly_1000_multibyte_characters_are_accepted(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => str_repeat('字', 1000)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_html_is_escaped_and_newlines_are_preserved(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($b, $a)->create(['body' => "<script>alert(1)</script>\n<b>bold</b>"]);

        $page = $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
        $this->assertStringContainsString('whitespace-pre-line', $page);
    }

    public function test_sending_is_rate_limited_to_twenty_per_minute(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => "m{$i}"])->assertRedirect();
        }

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'too many'])->assertStatus(429);
        $this->assertDatabaseCount('messages', 20);
    }

    public function test_the_reminder_email_has_no_body_and_is_throttled_per_conversation(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();
        RateLimiter::clear('member-message-notify:'.$b->id.':'.$a->id);

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '機密內容一']);
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '機密內容二']);

        Notification::assertSentToTimes($b, NewMessageNotification::class, 1);

        $mail = null;
        Notification::assertSentTo($b, NewMessageNotification::class, function (NewMessageNotification $notification) use ($b, &$mail) {
            $mail = $notification->toMail($b);

            return true;
        });

        $text = implode(' ', array_merge($mail->introLines, $mail->outroLines, [$mail->subject]));
        $this->assertStringNotContainsString('機密內容', $text);
        $this->assertStringContainsString('/messages/'.$friendship->id, $mail->actionUrl);
    }

    public function test_a_different_sender_is_throttled_separately(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();
        $c = Member::factory()->create();
        $second = Friendship::factory()->between($c, $b)->accepted()->create();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'a']);
        $this->actingAs($c, 'member')->post('/messages/'.$second->id, ['body' => 'c']);

        Notification::assertSentToTimes($b, NewMessageNotification::class, 2);
    }

    public function test_a_mail_failure_does_not_fail_the_send(): void
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
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])
            ->assertRedirect('/messages/'.$friendship->id);

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_removing_the_friend_hides_the_conversation_and_re_adding_restores_history(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->create(['body' => '舊訊息']);

        $friendship->removeBy($a);
        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();

        Friendship::factory()->between($a, $b)->accepted()->create();
        $new = Friendship::between($a, $b);

        $this->actingAs($b, 'member')->get('/messages/'.$new->id)->assertOk()->assertSee('舊訊息');
    }

    public function test_guests_and_unverified_members_are_redirected(): void
    {
        [, , $friendship] = $this->friends();

        $this->get('/messages/'.$friendship->id)->assertRedirect('/login');
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/messages/'.$friendship->id)
            ->assertRedirect('/email/verify');
    }
}

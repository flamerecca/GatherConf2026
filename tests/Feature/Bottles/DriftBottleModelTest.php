<?php

namespace Tests\Feature\Bottles;

use App\Models\DriftBottle;
use App\Models\DriftBottleReport;
use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriftBottleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_follows_the_columns(): void
    {
        $bottle = DriftBottle::factory()->create();
        $this->assertSame('in_sea', $bottle->status());

        $bottle->forceFill(['recipient_id' => Member::factory()->create()->id])->save();
        $this->assertSame('delivered', $bottle->status());

        $bottle->forceFill(['opened_at' => now()])->save();
        $this->assertSame('opened', $bottle->status());

        $bottle->forceFill(['replied_at' => now(), 'reply_body' => 'hi'])->save();
        $this->assertSame('replied', $bottle->status());

        $bottle->forceFill(['removed_at' => now()])->save();
        $this->assertSame('removed', $bottle->status());
    }

    public function test_throwing_assigns_a_stranger(): void
    {
        $sender = Member::factory()->create();
        $stranger = Member::factory()->create();

        $bottle = DriftBottle::throwFrom($sender, '哈囉');

        $this->assertTrue($bottle->recipient->is($stranger));
        $this->assertNotNull($bottle->assigned_at);
    }

    public function test_the_bottle_stays_in_the_sea_without_an_eligible_stranger(): void
    {
        $sender = Member::factory()->create();

        $bottle = DriftBottle::throwFrom($sender, '哈囉');

        $this->assertSame('in_sea', $bottle->status());
    }

    public function test_ineligible_members_never_receive_it(): void
    {
        $sender = Member::factory()->create();
        $friend = Member::factory()->create();
        $invited = Member::factory()->create();
        $blocked = Member::factory()->create();
        $blocker = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();
        $unverified = Member::factory()->unverified()->create();
        Friendship::factory()->between($sender, $friend)->accepted()->create();
        Friendship::factory()->between($sender, $invited)->create();
        MemberBlock::factory()->create(['blocker_id' => $sender->id, 'blocked_id' => $blocked->id]);
        MemberBlock::factory()->create(['blocker_id' => $blocker->id, 'blocked_id' => $sender->id]);

        // The factories above create stray members, so keep only the cast of this test.
        Member::query()
            ->whereNotIn('id', [$sender->id, $friend->id, $invited->id, $blocked->id, $blocker->id, $inactive->id, $unverified->id])
            ->delete();

        $first = DriftBottle::throwFrom($sender, '哈囉');
        $this->assertSame('in_sea', $first->status());

        $stranger = Member::factory()->create();
        $second = DriftBottle::throwFrom($sender, '哈囉');
        $this->assertTrue($second->recipient->is($stranger));
    }

    public function test_a_member_holding_five_unopened_bottles_is_skipped(): void
    {
        $sender = Member::factory()->create();
        $author = Member::factory()->inactive()->create();
        $holder = Member::factory()->create();
        DriftBottle::factory()->count(5)->create([
            'sender_id' => $author->id,
            'recipient_id' => $holder->id,
            'assigned_at' => now(),
        ]);

        $first = DriftBottle::throwFrom($sender, '哈囉');
        $this->assertSame('in_sea', $first->status());

        DriftBottle::query()->where('recipient_id', $holder->id)->limit(1)->update(['opened_at' => now()]);

        $second = DriftBottle::throwFrom($sender, '哈囉');
        $this->assertTrue($second->recipient->is($holder));
    }

    public function test_pick_takes_a_bottle_from_the_sea_and_never_your_own(): void
    {
        $sender = Member::factory()->create();
        $picker = Member::factory()->create();
        $own = DriftBottle::factory()->create(['sender_id' => $picker->id]);
        $other = DriftBottle::factory()->create(['sender_id' => $sender->id]);

        $picked = DriftBottle::pick($picker);

        $this->assertTrue($picked->is($other));
        $this->assertTrue($picked->recipient->is($picker));
        $this->assertSame('in_sea', $own->fresh()->status());
    }

    public function test_pick_skips_friends_blocked_inactive_senders_and_released_ones(): void
    {
        $picker = Member::factory()->create();
        $friend = Member::factory()->create();
        $blocked = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();
        $other = Member::factory()->create();
        Friendship::factory()->between($picker, $friend)->accepted()->create();
        MemberBlock::factory()->create(['blocker_id' => $picker->id, 'blocked_id' => $blocked->id]);
        DriftBottle::factory()->create(['sender_id' => $friend->id]);
        DriftBottle::factory()->create(['sender_id' => $blocked->id]);
        DriftBottle::factory()->create(['sender_id' => $inactive->id]);
        DriftBottle::factory()->create(['sender_id' => $other->id, 'released_recipient_ids' => [$picker->id]]);
        DriftBottle::factory()->create(['sender_id' => $other->id, 'removed_at' => now()]);

        $this->assertNull(DriftBottle::pick($picker));
    }

    public function test_only_one_member_wins_a_contested_bottle(): void
    {
        $bottle = DriftBottle::factory()->create();
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $first = DriftBottle::pick($a);
        $second = DriftBottle::pick($b);

        $this->assertTrue($first->is($bottle));
        $this->assertNull($second);
        $this->assertTrue($bottle->fresh()->recipient->is($a));
    }

    public function test_release_returns_unopened_bottles_and_remembers_the_recipient(): void
    {
        $recipient = Member::factory()->create();
        $bottle = DriftBottle::factory()->create([
            'recipient_id' => $recipient->id,
            'assigned_at' => now()->subHours(25),
        ]);

        $this->artisan('drift-bottles:release')->assertSuccessful();

        $fresh = $bottle->fresh();
        $this->assertNull($fresh->recipient_id);
        $this->assertNull($fresh->assigned_at);
        $this->assertSame([$recipient->id], $fresh->released_recipient_ids);
    }

    public function test_release_leaves_recent_opened_and_removed_bottles(): void
    {
        $recipient = Member::factory()->create();
        $old = now()->subHours(25);
        $recent = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()->subHours(2)]);
        $opened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => $old, 'opened_at' => now()]);
        $removed = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => $old, 'removed_at' => now()]);

        $this->artisan('drift-bottles:release')->assertSuccessful();

        foreach ([$recent, $opened, $removed] as $bottle) {
            $this->assertNotNull($bottle->fresh()->recipient_id);
        }
    }

    public function test_only_the_recipient_opens_and_replies_once(): void
    {
        $recipient = Member::factory()->create();
        $stranger = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()]);

        $this->assertFalse($bottle->replyFrom($recipient, '早'));

        $bottle->open($stranger);
        $this->assertNull($bottle->fresh()->opened_at);

        $bottle->open($recipient);
        $this->assertNotNull($bottle->fresh()->opened_at);

        $this->assertFalse($bottle->fresh()->replyFrom($stranger, '偷回'));
        $this->assertTrue($bottle->fresh()->replyFrom($recipient, '收到'));
        $this->assertFalse($bottle->fresh()->replyFrom($recipient, '再回'));
        $this->assertSame('收到', $bottle->fresh()->reply_body);
    }

    public function test_a_removed_bottle_cannot_be_replied_to(): void
    {
        $recipient = Member::factory()->create();
        $bottle = DriftBottle::factory()->create([
            'recipient_id' => $recipient->id, 'opened_at' => now(), 'removed_at' => now(),
        ]);

        $this->assertFalse($bottle->replyFrom($recipient, '收到'));
    }

    public function test_reporting_hides_the_content_and_is_recorded_once(): void
    {
        $recipient = Member::factory()->create();
        $bottle = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'opened_at' => now()]);

        $this->assertTrue($bottle->reportBy($recipient, 'bottle', '不當內容'));
        $this->assertFalse($bottle->fresh()->reportBy($recipient, 'bottle'));

        $this->assertNotNull($bottle->fresh()->removed_at);
        $this->assertSame(1, DriftBottleReport::query()->count());
    }

    public function test_only_the_sender_can_report_a_reply(): void
    {
        $recipient = Member::factory()->create();
        $bottle = DriftBottle::factory()->create([
            'recipient_id' => $recipient->id, 'opened_at' => now(),
            'replied_at' => now(), 'reply_body' => '回覆',
        ]);

        $this->assertFalse($bottle->reportBy($recipient, 'reply'));
        $this->assertTrue($bottle->reportBy($bottle->sender, 'reply'));
        $this->assertNotNull($bottle->fresh()->reply_removed_at);
        $this->assertNull($bottle->fresh()->removed_at);
    }

    public function test_contact_information_is_detected(): void
    {
        foreach (['https://example.com', 'www.example.com', 'a@b.co', 'HTTP://X.TW'] as $text) {
            $this->assertTrue(DriftBottle::containsContactInfo("看 $text 吧"), $text);
        }

        $this->assertFalse(DriftBottle::containsContactInfo('今天天氣很好'));
    }

    public function test_counters(): void
    {
        $sender = Member::factory()->create();
        $holder = Member::factory()->create();
        DriftBottle::factory()->count(2)->create(['sender_id' => $sender->id]);
        DriftBottle::factory()->create(['sender_id' => $sender->id, 'created_at' => now()->subDays(2)]);
        DriftBottle::factory()->create(['recipient_id' => $holder->id]);
        DriftBottle::factory()->create(['recipient_id' => $holder->id, 'opened_at' => now()]);

        $this->assertSame(2, DriftBottle::throwsToday($sender));
        $this->assertSame(1, DriftBottle::heldCount($holder));
    }

    public function test_deleting_a_recipient_returns_unopened_bottles_to_the_sea(): void
    {
        $recipient = Member::factory()->create();
        $unopened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()]);
        $opened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'opened_at' => now()]);

        $recipient->delete();

        $this->assertSame('in_sea', $unopened->fresh()->status());
        $this->assertSame('opened', $opened->fresh()->status());
    }

    public function test_deleting_a_sender_deletes_the_bottle(): void
    {
        $bottle = DriftBottle::factory()->create();

        $bottle->sender->delete();

        $this->assertNull(DriftBottle::query()->find($bottle->id));
    }
}

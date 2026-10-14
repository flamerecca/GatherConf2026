<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Member, 1: Member}
     */
    private function friends(): array
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        return [$a, $b];
    }

    public function test_friends_can_message_each_other(): void
    {
        [$a, $b] = $this->friends();

        $this->assertTrue($a->canMessage($b));
        $this->assertTrue($b->canMessage($a));
    }

    public function test_strangers_pending_blocked_inactive_and_self_cannot_message(): void
    {
        [$a, $b] = $this->friends();
        $stranger = Member::factory()->create();
        $pending = Member::factory()->create();
        Friendship::factory()->between($a, $pending)->create();

        $this->assertFalse($a->canMessage($stranger));
        $this->assertFalse($a->canMessage($pending));
        $this->assertFalse($a->canMessage($a));

        $b->update(['is_active' => false]);
        $this->assertFalse($a->fresh()->canMessage($b->fresh()));
        $this->assertFalse($b->fresh()->canMessage($a->fresh()));

        $b->update(['is_active' => true]);
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);
        $this->assertFalse($a->canMessage($b));
        $this->assertFalse($b->canMessage($a));
    }

    public function test_send_from_stores_the_message_unread(): void
    {
        [$a, $b] = $this->friends();

        $message = Message::sendFrom($a, $b, 'hello');

        $this->assertSame('hello', $message->body);
        $this->assertSame($a->id, $message->sender_id);
        $this->assertSame($b->id, $message->recipient_id);
        $this->assertNull($message->read_at);
    }

    public function test_send_from_returns_null_when_not_allowed(): void
    {
        $a = Member::factory()->create();
        $stranger = Member::factory()->create();

        $this->assertNull(Message::sendFrom($a, $stranger, 'hi'));
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_mark_read_only_touches_messages_sent_to_the_reader(): void
    {
        [$a, $b] = $this->friends();
        $toA = Message::factory()->from($b, $a)->create();
        $toB = Message::factory()->from($a, $b)->create();

        $this->assertSame(1, Message::markRead($a, $b));

        $this->assertNotNull($toA->fresh()->read_at);
        $this->assertNull($toB->fresh()->read_at);
        $this->assertSame(0, Message::markRead($a, $b));
    }

    public function test_latest_page_is_oldest_first_and_capped(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();

        $page = Message::latestPage($a, $b);

        $this->assertCount(Message::PAGE_SIZE, $page);
        $this->assertSame($page->sortBy('id')->pluck('id')->all(), $page->pluck('id')->all());
        $this->assertSame(Message::query()->max('id'), $page->last()->id);
    }

    public function test_latest_page_can_load_older_messages(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();
        $first = Message::latestPage($a, $b);

        $older = Message::latestPage($a, $b, $first->first()->id);

        $this->assertCount(5, $older);
        $this->assertTrue($older->last()->id < $first->first()->id);
    }

    public function test_conversation_includes_both_directions_only_for_the_pair(): void
    {
        [$a, $b] = $this->friends();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $c)->accepted()->create();
        Message::factory()->from($a, $b)->create();
        Message::factory()->from($b, $a)->create();
        Message::factory()->from($a, $c)->create();

        $this->assertCount(2, Message::latestPage($a, $b));
        $this->assertCount(2, Message::latestPage($b, $a));
    }

    public function test_unread_count_only_counts_current_messageable_friends(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $blocked = Member::factory()->create();
        $inactive = Member::factory()->create();
        $removed = Member::factory()->create();
        $stranger = Member::factory()->create();
        foreach ([$friend, $blocked, $inactive] as $other) {
            Friendship::factory()->between($me, $other)->accepted()->create();
        }
        foreach ([$friend, $blocked, $inactive, $removed, $stranger] as $sender) {
            Message::factory()->from($sender, $me)->create();
        }
        Message::factory()->from($friend, $me)->read()->create();
        Message::factory()->from($me, $friend)->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);
        $inactive->update(['is_active' => false]);

        $this->assertSame(1, $me->fresh()->unreadMessageCount());
        $this->assertSame(1, $me->fresh()->unreadMessageCountFrom($friend));
        $this->assertSame(0, $me->fresh()->unreadMessageCountFrom($blocked));
    }

    public function test_messages_are_removed_with_the_member(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->create();

        $a->delete();

        $this->assertDatabaseCount('messages', 0);
    }
}

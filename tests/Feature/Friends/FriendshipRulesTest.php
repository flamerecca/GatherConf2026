<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_creates_a_pending_friendship_from_the_inviter(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->assertSame(Friendship::INVITE_INVITED, Friendship::invite($a, $b));

        $friendship = Friendship::between($a, $b);
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertSame($a->id, $friendship->requester_id);
    }

    public function test_inviting_twice_does_not_create_a_second_row(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        $this->assertSame(Friendship::INVITE_ALREADY_INVITED, Friendship::invite($a, $b));
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_inviting_someone_who_already_invited_you_accepts_it(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        $this->assertSame(Friendship::INVITE_ACCEPTED, Friendship::invite($b, $a));

        $friendship = Friendship::between($a, $b);
        $this->assertTrue($friendship->isAccepted());
        $this->assertNotNull($friendship->responded_at);
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_existing_friends_are_not_invited_again(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        $this->assertSame(Friendship::INVITE_ALREADY_FRIENDS, Friendship::invite($a, $b));
        $this->assertSame(Friendship::INVITE_ALREADY_FRIENDS, Friendship::invite($b, $a));
    }

    public function test_you_cannot_invite_yourself(): void
    {
        $a = Member::factory()->create();

        $this->assertSame(Friendship::INVITE_SELF, Friendship::invite($a, $a));
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_blocks_in_either_direction_prevent_invitations(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        MemberBlock::block($a, $b);

        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($a, $b));
        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($b, $a));
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_only_the_invitee_can_accept(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->assertFalse($friendship->acceptBy($a));
        $this->assertFalse($friendship->acceptBy($outsider));
        $this->assertTrue($friendship->acceptBy($b));
        $this->assertTrue($friendship->fresh()->isAccepted());
    }

    public function test_accepting_twice_is_harmless(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->assertTrue($friendship->acceptBy($b));
        $this->assertFalse(Friendship::between($a, $b)->acceptBy($b));
    }

    public function test_either_participant_can_remove_but_outsiders_cannot(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        $this->assertFalse(Friendship::between($a, $b)->removeBy($outsider));
        $this->assertTrue(Friendship::between($a, $b)->removeBy($b));
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_declining_and_cancelling_delete_the_pending_row_and_allow_a_new_invitation(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        Friendship::between($a, $b)->removeBy($b);

        $this->assertSame(Friendship::INVITE_INVITED, Friendship::invite($a, $b));
    }

    public function test_blocking_removes_the_friendship_and_is_idempotent(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        MemberBlock::block($a, $b);
        MemberBlock::block($a, $b);

        $this->assertNull(Friendship::between($a, $b));
        $this->assertTrue($a->hasBlocked($b));
        $this->assertTrue($b->isBlockedBy($a));
        $this->assertFalse($b->hasBlocked($a));
        $this->assertDatabaseCount('member_blocks', 1);
    }

    public function test_unblocking_does_not_restore_the_friendship(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        MemberBlock::block($a, $b);

        MemberBlock::unblock($a, $b);

        $this->assertFalse($a->hasBlocked($b));
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_can_block_only_friends_and_pending_inviters(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $inviter = Member::factory()->create();
        $invitee = Member::factory()->create();
        $stranger = Member::factory()->create();
        Friendship::factory()->between($me, $friend)->accepted()->create();
        Friendship::factory()->between($inviter, $me)->create();
        Friendship::factory()->between($me, $invitee)->create();

        $this->assertTrue($me->canBlock($friend));
        $this->assertTrue($me->canBlock($inviter));
        $this->assertFalse($me->canBlock($invitee));
        $this->assertFalse($me->canBlock($stranger));
        $this->assertFalse($me->canBlock($me));
    }

    public function test_is_friend_with(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        Friendship::factory()->between($a, $c)->create();

        $this->assertTrue($a->isFriendWith($b));
        $this->assertTrue($b->isFriendWith($a));
        $this->assertFalse($a->isFriendWith($c));
    }
}

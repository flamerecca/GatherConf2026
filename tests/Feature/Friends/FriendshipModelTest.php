<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_between_finds_the_pair_regardless_of_order(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertTrue(Friendship::between($a, $b)->is($friendship));
        $this->assertTrue(Friendship::between($b, $a)->is($friendship));
        $this->assertNull(Friendship::between($a, Member::factory()->create()));
    }

    public function test_the_lower_id_is_always_stored_first(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $friendship = Friendship::factory()->between($b, $a)->create();

        $this->assertSame($a->id, $friendship->member_low_id);
        $this->assertSame($b->id, $friendship->member_high_id);
        $this->assertSame($b->id, $friendship->requester_id);
    }

    public function test_a_pair_can_only_exist_once(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Friendship::factory()->between($b, $a)->create();
    }

    public function test_status_helpers_and_scopes(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        Friendship::factory()->between($a, $c)->create();

        $this->assertCount(1, Friendship::query()->accepted()->involving($a)->get());
        $this->assertCount(1, Friendship::query()->pending()->involving($a)->get());
        $this->assertCount(2, Friendship::query()->involving($a)->get());
        $this->assertCount(1, Friendship::query()->involving($b)->get());
    }

    public function test_participant_and_other_member(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertTrue($friendship->isParticipant($a));
        $this->assertTrue($friendship->isParticipant($b));
        $this->assertFalse($friendship->isParticipant($outsider));
        $this->assertTrue($friendship->otherMember($a)->is($b));
        $this->assertTrue($friendship->otherMember($b)->is($a));
    }

    public function test_rows_are_removed_with_the_member(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->create();
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);

        $a->delete();

        $this->assertDatabaseCount('friendships', 0);
        $this->assertDatabaseCount('member_blocks', 0);
    }

    public function test_a_block_pair_is_unique(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);
    }
}

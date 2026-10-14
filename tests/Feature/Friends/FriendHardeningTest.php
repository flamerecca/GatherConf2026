<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FriendHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_backfill_covers_more_than_one_chunk_of_members(): void
    {
        $migration = require glob(database_path('migrations/*_add_friend_code_to_members_table.php'))[0];
        $migration->down();

        $now = now()->toDateTimeString();
        collect(range(1, 1200))->chunk(200)->each(function ($chunk) use ($now): void {
            DB::table('members')->insert($chunk->map(fn (int $i) => [
                'name' => "Member {$i}",
                'email' => "member{$i}@example.com",
                'password' => 'x',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        $migration->up();

        $this->assertSame(0, DB::table('members')->whereNull('friend_code')->count());
        $this->assertSame(1200, DB::table('members')->distinct()->count('friend_code'));
    }

    public function test_accepting_is_refused_when_the_requester_has_been_deactivated(): void
    {
        $requester = Member::factory()->create();
        $me = Member::factory()->create();
        Friendship::invite($requester, $me);
        $requester->update(['is_active' => false]);

        $this->actingAs($me, 'member')
            ->post('/friends/'.Friendship::between($requester, $me)->id.'/accept')
            ->assertNotFound();

        $this->assertFalse(Friendship::between($requester, $me)->isAccepted());
    }

    public function test_accepting_is_refused_when_a_block_exists_in_either_direction(): void
    {
        $requester = Member::factory()->create();
        $me = Member::factory()->create();
        Friendship::factory()->between($requester, $me)->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $requester->id]);

        $this->assertFalse(Friendship::between($requester, $me)->acceptBy($me));

        MemberBlock::query()->delete();
        MemberBlock::factory()->create(['blocker_id' => $requester->id, 'blocked_id' => $me->id]);

        $this->assertFalse(Friendship::between($requester, $me)->acceptBy($me));
    }

    public function test_a_pending_row_that_slipped_past_a_block_is_not_listed(): void
    {
        $me = Member::factory()->create();
        $stale = Member::factory()->create(['name' => '漏網之魚']);
        Friendship::factory()->between($stale, $me)->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $stale->id]);

        $this->actingAs($me, 'member')->get('/friends')
            ->assertSee('漏網之魚', false) // shown only once, in the blocked list
            ->assertDontSee('data-received', false);

        $page = $this->actingAs($me, 'member')->get('/friends')->getContent();
        $this->assertSame(1, substr_count($page, '漏網之魚'));
    }

    public function test_the_center_count_ignores_blocked_and_inactive_inviters(): void
    {
        $me = Member::factory()->create();
        $blocked = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();
        $ok = Member::factory()->create();
        Friendship::factory()->between($blocked, $me)->create();
        Friendship::factory()->between($inactive, $me)->create();
        Friendship::factory()->between($ok, $me)->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-pending-invitations="1"', false);
    }

    public function test_inviting_an_inactive_member_is_refused_in_the_model(): void
    {
        $a = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();

        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($a, $inactive));
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_a_block_created_during_the_invite_removes_the_new_row(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        Friendship::creating(function (Friendship $friendship) use ($a, $b): void {
            MemberBlock::query()->create(['blocker_id' => $b->id, 'blocked_id' => $a->id]);
        });

        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($a, $b));
        $this->assertNull(Friendship::between($a, $b));

        Friendship::flushEventListeners();
    }
}

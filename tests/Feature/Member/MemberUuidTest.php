<?php

namespace Tests\Feature\Member;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\MemberLoginLink;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberUuidTest extends TestCase
{
    use RefreshDatabase;

    private const UUID7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function test_member_ids_are_version_7_uuids(): void
    {
        $member = Member::factory()->create();

        $this->assertMatchesRegularExpression(self::UUID7, $member->id);
        $this->assertSame($member->id, $member->getKey());
        $this->assertFalse($member->getIncrementing());
    }

    public function test_the_timestamp_prefix_never_goes_backwards(): void
    {
        $ids = collect(range(1, 5))->map(function () {
            usleep(2000);

            return Member::factory()->create()->id;
        });

        $prefixes = $ids->map(fn (string $id) => substr(str_replace('-', '', $id), 0, 12));

        $this->assertSame($prefixes->sort()->values()->all(), $prefixes->values()->all());
    }

    public function test_every_member_foreign_key_column_is_not_an_integer(): void
    {
        $columns = [
            'members' => ['id'],
            'member_login_links' => ['member_id'],
            'friendships' => ['member_low_id', 'member_high_id', 'requester_id'],
            'member_blocks' => ['blocker_id', 'blocked_id'],
            'messages' => ['sender_id', 'recipient_id'],
            'quiz_attempts' => ['user_id'],
        ];

        foreach ($columns as $table => $names) {
            $types = collect(Schema::getColumns($table))->pluck('type_name', 'name');

            foreach ($names as $name) {
                $this->assertStringNotContainsString('int', strtolower($types[$name]), "{$table}.{$name} is {$types[$name]}");
            }
        }
    }

    public function test_the_whole_member_graph_works_with_uuid_keys(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();
        $message = Message::sendFrom($a, $b, 'hi');
        $link = MemberLoginLink::factory()->for($a)->create();
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => Member::factory()->create()->id]);

        $this->assertTrue($friendship->otherMember($a)->is($b));
        $this->assertSame($a->id, $message->sender_id);
        $this->assertTrue($link->member->is($a));
        $this->assertCount(1, $a->loginLinks);
    }

    public function test_the_lower_uuid_is_always_stored_first(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $friendship = Friendship::factory()->between($b, $a)->create();

        $this->assertTrue($friendship->member_low_id < $friendship->member_high_id);
        $this->assertSame(collect([$a->id, $b->id])->sort()->values()->all(), [$friendship->member_low_id, $friendship->member_high_id]);
        $this->assertSame($b->id, $friendship->requester_id);
    }

    public function test_routes_resolve_by_uuid_and_reject_integers(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        Friendship::factory()->between($me, $friend)->accepted()->create();

        $this->actingAs($me, 'member')->post('/friends/'.$friend->id.'/block')->assertRedirect('/friends');
        $this->actingAs($me, 'member')->delete('/friends/1/block')->assertNotFound();
        $this->actingAs($me, 'member')->delete('/friends/not-a-uuid/block')->assertNotFound();
    }

    public function test_friend_codes_and_uuids_are_independent(): void
    {
        $member = Member::factory()->create();

        $this->assertNotSame($member->id, $member->friend_code);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $member->friend_code);
    }
}

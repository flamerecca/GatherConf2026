<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_friends_page_links_to_each_conversation_with_the_unread_count(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '好友甲']);
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        Message::factory()->from($friend, $me)->count(3)->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertSee(route('member.messages.show', $friendship), false)
            ->assertSee('data-unread="3"', false);

        $this->actingAs($me, 'member')->get('/en/friends')
            ->assertSee(route('member.messages.show.en', $friendship), false);
    }

    public function test_friends_page_does_not_link_to_conversations_with_blocked_members(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $friend->id]);

        $this->actingAs($me, 'member')->get('/friends')
            ->assertDontSee(route('member.messages.show', $friendship), false);
    }

    public function test_member_center_shows_the_total_unread_count(): void
    {
        $me = Member::factory()->create();
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($me, $a)->accepted()->create();
        Friendship::factory()->between($me, $b)->accepted()->create();
        Message::factory()->from($a, $me)->count(2)->create();
        Message::factory()->from($b, $me)->create();
        Message::factory()->from($b, $me)->read()->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-unread-messages="3"', false);
    }

    public function test_opening_a_conversation_clears_its_unread_count(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        Message::factory()->from($friend, $me)->count(2)->create();

        $this->actingAs($me, 'member')->get('/messages/'.$friendship->id)->assertOk();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-unread-messages="0"', false);
    }
}

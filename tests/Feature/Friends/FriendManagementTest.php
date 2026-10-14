<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_page_shows_my_qr_code_and_link_in_both_languages(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertOk()
            ->assertSee('我的好友')
            ->assertSee(url('/friends/add/'.$me->friend_code), false)
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('noindex', false);

        $this->actingAs($me, 'member')->get('/en/friends')
            ->assertOk()
            ->assertSee('My friends')
            ->assertSee(url('/en/friends/add/'.$me->friend_code), false);
    }

    public function test_page_lists_friends_and_both_kinds_of_invitations(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '好友甲']);
        $inviter = Member::factory()->create(['name' => '邀請者乙']);
        $invitee = Member::factory()->create(['name' => '受邀者丙']);
        Friendship::factory()->between($me, $friend)->accepted()->create();
        Friendship::factory()->between($inviter, $me)->create();
        Friendship::factory()->between($me, $invitee)->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertSee('好友甲')
            ->assertSee('邀請者乙')
            ->assertSee('受邀者丙');
    }

    public function test_inactive_members_are_hidden_from_every_list(): void
    {
        $me = Member::factory()->create();
        $gone = Member::factory()->inactive()->create(['name' => '已停用者']);
        Friendship::factory()->between($me, $gone)->accepted()->create();

        $this->actingAs($me, 'member')->get('/friends')->assertDontSee('已停用者');
    }

    public function test_other_members_data_is_not_shown(): void
    {
        $me = Member::factory()->create();
        $stranger = Member::factory()->create(['name' => '陌生人', 'email' => 'stranger@example.com']);
        $a = Member::factory()->create();
        Friendship::factory()->between($stranger, $a)->accepted()->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertDontSee('陌生人')
            ->assertDontSee('stranger@example.com');
    }

    public function test_regenerating_the_code_changes_it(): void
    {
        $me = Member::factory()->create();
        $old = $me->friend_code;

        $this->actingAs($me, 'member')->post('/friends/code')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.regenerated'));

        $this->assertNotSame($old, $me->fresh()->friend_code);
    }

    public function test_the_invitee_can_accept(): void
    {
        $me = Member::factory()->create();
        $inviter = Member::factory()->create();
        Friendship::invite($inviter, $me);
        $friendship = Friendship::between($inviter, $me);

        $this->actingAs($me, 'member')->post('/friends/'.$friendship->id.'/accept')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.accepted'));

        $this->assertTrue($friendship->fresh()->isAccepted());
    }

    public function test_the_inviter_cannot_accept_their_own_invitation(): void
    {
        $me = Member::factory()->create();
        $invitee = Member::factory()->create();
        Friendship::invite($me, $invitee);
        $friendship = Friendship::between($me, $invitee);

        $this->actingAs($me, 'member')->post('/friends/'.$friendship->id.'/accept')->assertNotFound();

        $this->assertFalse($friendship->fresh()->isAccepted());
    }

    public function test_outsiders_get_404_for_accept_and_remove(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->actingAs($outsider, 'member')->post('/friends/'.$friendship->id.'/accept')->assertNotFound();
        $this->actingAs($outsider, 'member')->delete('/friends/'.$friendship->id)->assertNotFound();

        $this->assertNotNull($friendship->fresh());
    }

    public function test_either_side_can_ignore_cancel_or_remove(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        Friendship::factory()->between($other, $me)->accepted()->create();

        $this->actingAs($me, 'member')->delete('/friends/'.Friendship::between($me, $other)->id)
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.removed'));

        $this->assertNull(Friendship::between($me, $other));
    }

    public function test_blocking_a_friend_removes_the_friendship_and_lists_them_as_blocked(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '被封鎖者']);
        Friendship::factory()->between($me, $friend)->accepted()->create();

        $this->actingAs($me, 'member')->post('/friends/'.$friend->id.'/block')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.blocked'));

        $this->assertNull(Friendship::between($me, $friend));
        $this->assertTrue($me->hasBlocked($friend));
        $this->actingAs($me, 'member')->get('/friends')->assertSee('被封鎖者');
    }

    public function test_blocking_a_pending_inviter_is_allowed(): void
    {
        $me = Member::factory()->create();
        $inviter = Member::factory()->create();
        Friendship::invite($inviter, $me);

        $this->actingAs($me, 'member')->post('/friends/'.$inviter->id.'/block')->assertRedirect('/friends');

        $this->assertTrue($me->hasBlocked($inviter));
        $this->assertNull(Friendship::between($me, $inviter));
    }

    public function test_blocking_a_stranger_by_id_returns_404(): void
    {
        $me = Member::factory()->create();
        $stranger = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/friends/'.$stranger->id.'/block')->assertNotFound();

        $this->assertFalse($me->hasBlocked($stranger));
    }

    public function test_unblocking_removes_the_block_only_for_the_blocker(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        MemberBlock::block($me, $other);

        $this->actingAs($other, 'member')->delete('/friends/'.$me->id.'/block')->assertNotFound();
        $this->assertTrue($me->hasBlocked($other));

        $this->actingAs($me, 'member')->delete('/friends/'.$other->id.'/block')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.unblocked'));

        $this->assertFalse($me->hasBlocked($other));
    }

    public function test_english_actions_redirect_to_the_english_page(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/en/friends/code')->assertRedirect('/en/friends');
    }
}

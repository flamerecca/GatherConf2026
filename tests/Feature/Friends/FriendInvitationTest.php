<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_add_page_shows_name_and_organization_but_not_email(): void
    {
        $owner = Member::factory()->create(['name' => '陳小華', 'organization' => 'Kotlin Taiwan', 'email' => 'secret@example.com']);
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->get('/friends/add/'.$owner->friend_code)
            ->assertOk()
            ->assertSee('陳小華')
            ->assertSee('Kotlin Taiwan')
            ->assertSee('送出好友邀請')
            ->assertDontSee('secret@example.com')
            ->assertSee('noindex', false);
    }

    public function test_english_add_page(): void
    {
        $owner = Member::factory()->create(['name' => 'Alice']);
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->get('/en/friends/add/'.$owner->friend_code)
            ->assertOk()
            ->assertSee('Send friend request')
            ->assertDontSee('送出好友邀請');
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/friends/add/not-a-code')->assertNotFound();
    }

    public function test_a_regenerated_code_invalidates_the_old_link(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();
        $old = $owner->friend_code;

        $owner->regenerateFriendCode();

        $this->actingAs($visitor, 'member')->get('/friends/add/'.$old)->assertNotFound();
        $this->actingAs($visitor, 'member')->get('/friends/add/'.$owner->fresh()->friend_code)->assertOk();
    }

    public function test_inactive_owner_returns_404(): void
    {
        $owner = Member::factory()->inactive()->create();

        $this->actingAs(Member::factory()->create(), 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
    }

    public function test_blocks_in_either_direction_return_404(): void
    {
        $owner = Member::factory()->create();
        $blockedByOwner = Member::factory()->create();
        $blockerOfOwner = Member::factory()->create();
        MemberBlock::block($owner, $blockedByOwner);
        MemberBlock::block($blockerOfOwner, $owner);

        $this->actingAs($blockedByOwner, 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
        $this->actingAs($blockerOfOwner, 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
        $this->actingAs($blockedByOwner, 'member')->post('/friends/add/'.$owner->friend_code)->assertNotFound();
    }

    public function test_own_link_shows_a_notice_without_an_invite_button(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/friends/add/'.$me->friend_code)
            ->assertOk()
            ->assertSee('這是你自己的連結')
            ->assertDontSee('送出好友邀請');
    }

    public function test_guests_are_sent_to_login_and_come_back_after_logging_in(): void
    {
        $owner = Member::factory()->create();
        Member::factory()->create(['email' => 'v@example.com']);
        $path = '/friends/add/'.$owner->friend_code;

        $this->get($path)->assertRedirect('/login');

        $this->withSession(['url.intended' => url($path)])
            ->post('/login', ['email' => 'v@example.com', 'password' => 'password'])
            ->assertRedirect(url($path));
    }

    public function test_unverified_members_are_sent_to_verification_first(): void
    {
        $owner = Member::factory()->create();

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/friends/add/'.$owner->friend_code)
            ->assertRedirect('/email/verify');
    }

    public function test_posting_creates_a_pending_invitation(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.results.invited'));

        $friendship = Friendship::between($owner, $visitor);
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertSame($visitor->id, $friendship->requester_id);
    }

    public function test_posting_to_someone_who_already_invited_you_accepts(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();
        Friendship::invite($owner, $visitor);

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertSessionHas('status', __('member.friends.results.accepted'));

        $this->assertTrue(Friendship::between($owner, $visitor)->isAccepted());
    }

    public function test_posting_twice_does_not_duplicate(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code);
        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertSessionHas('status', __('member.friends.results.already_invited'));

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_posting_to_your_own_code_is_rejected(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/friends/add/'.$me->friend_code)->assertRedirect('/friends');

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_english_post_redirects_to_the_english_friends_page(): void
    {
        $owner = Member::factory()->create();

        $this->actingAs(Member::factory()->create(), 'member')
            ->post('/en/friends/add/'.$owner->friend_code)
            ->assertRedirect('/en/friends');
    }
}

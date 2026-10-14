<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_member_center_links_to_friends_in_both_languages(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee(route('member.friends'), false);
        $this->actingAs($me, 'member')->get('/en/member')->assertSee(route('member.friends.en'), false);
    }

    public function test_member_center_shows_the_pending_invitation_count(): void
    {
        $me = Member::factory()->create();
        Friendship::factory()->between(Member::factory()->create(), $me)->create();
        Friendship::factory()->between(Member::factory()->create(), $me)->create();
        Friendship::factory()->between($me, Member::factory()->create())->create();
        Friendship::factory()->between(Member::factory()->inactive()->create(), $me)->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-pending-invitations="2"', false);
    }

    public function test_a_login_link_returns_to_the_page_the_member_wanted(): void
    {
        $owner = Member::factory()->create();
        $member = Member::factory()->create();
        $path = '/friends/add/'.$owner->friend_code;

        $this->get($path)->assertRedirect('/login');

        $this->withSession(['url.intended' => url($path)])
            ->get('/login/link/'.MemberLoginLink::issueFor($member))
            ->assertRedirect(url($path));
    }

    public function test_a_login_link_without_a_target_goes_to_the_center(): void
    {
        $member = Member::factory()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');
    }
}

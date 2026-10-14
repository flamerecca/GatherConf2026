<?php

namespace Tests\Feature\Friends;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FriendRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_friend_route_exists_in_both_languages(): void
    {
        $paths = [
            'member.friends' => '/friends',
            'member.friends.code.regenerate' => '/friends/code',
            'member.friends.add' => '/friends/add/{code}',
            'member.friends.invite' => '/friends/add/{code}',
            'member.friends.accept' => '/friends/{friendship}/accept',
            'member.friends.decline' => '/friends/{friendship}',
            'member.friends.block' => '/friends/{member}/block',
            'member.friends.unblock' => '/friends/{member}/block',
        ];

        foreach ($paths as $name => $path) {
            $this->assertSame($path, '/'.Route::getRoutes()->getByName($name)->uri());
            $this->assertSame('/en'.$path, '/'.Route::getRoutes()->getByName($name.'.en')->uri());
        }
    }

    public function test_friend_routes_require_login_and_verified_email(): void
    {
        $middleware = Route::getRoutes()->getByName('member.friends')->gatherMiddleware();

        $this->assertContains('auth:member', $middleware);
        $this->assertContains('member.verified', $middleware);
        $this->assertContains('member.locale:zh_TW', $middleware);
    }

    public function test_unverified_members_are_sent_to_the_verification_notice(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->get('/friends')->assertRedirect('/email/verify');
        $this->actingAs($member, 'member')->get('/en/friends')->assertRedirect('/en/email/verify');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/friends')->assertRedirect('/login');
        $this->get('/en/friends')->assertRedirect('/en/login');
    }

    public function test_inviting_is_rate_limited_per_member(): void
    {
        $this->assertContains('throttle:member-invite', Route::getRoutes()->getByName('member.friends.invite')->gatherMiddleware());
    }
}

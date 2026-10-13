<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'member.locale:en'])->get('/en/_probe-locale', fn () => app()->getLocale());
        Route::middleware(['web', 'member.locale:zh_TW'])->get('/_probe-locale', fn () => app()->getLocale());
        Route::middleware(['web', 'member.active', 'auth:member'])->get('/_probe-auth', fn () => 'ok');
        Route::middleware(['web', 'member.active', 'auth:member'])->get('/en/_probe-auth', fn () => 'ok');
        Route::middleware(['web', 'guest:member'])->get('/_probe-guest', fn () => 'guest');
        Route::middleware(['web', 'guest:member'])->get('/en/_probe-guest', fn () => 'guest');
        Route::get('/login', fn () => 'login')->name('member.login');
        Route::get('/en/login', fn () => 'login')->name('member.login.en');
        Route::get('/member', fn () => 'center')->name('member.center');
        Route::get('/en/member', fn () => 'center')->name('member.center.en');

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_set_locale_applies_the_middleware_parameter(): void
    {
        $this->get('/en/_probe-locale')->assertSee('en');
        $this->get('/_probe-locale')->assertSee('zh_TW');
    }

    public function test_guests_are_redirected_to_the_matching_login_page(): void
    {
        $this->get('/_probe-auth')->assertRedirect(route('member.login'));
        $this->get('/en/_probe-auth')->assertRedirect(route('member.login.en'));
    }

    public function test_logged_in_members_are_redirected_away_from_guest_pages(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->get('/_probe-guest')->assertRedirect(route('member.center'));
        $this->actingAs($member, 'member')->get('/en/_probe-guest')->assertRedirect(route('member.center.en'));
    }

    public function test_a_deactivated_member_is_logged_out_on_the_next_request(): void
    {
        $member = Member::factory()->create();
        $this->actingAs($member, 'member');
        $this->get('/_probe-auth')->assertOk();

        $member->update(['is_active' => false]);

        $this->get('/_probe-auth')->assertRedirect(route('member.login'));
        $this->assertGuest('member');
    }

    public function test_active_members_pass_through(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/_probe-auth')->assertOk();
        $this->assertTrue(Auth::guard('member')->check());
    }
}

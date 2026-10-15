<?php

namespace Tests\Feature\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_route_exists_in_both_languages_with_matching_paths(): void
    {
        $names = [
            'member.register' => '/register',
            'member.register.store' => '/register',
            'member.login' => '/login',
            'member.login.store' => '/login',
            'member.login.link.request' => '/login/link',
            'member.password.request' => '/forgot-password',
            'member.password.email' => '/forgot-password',
            'member.password.update' => '/reset-password',
            'member.logout' => '/logout',
            'member.center' => '/member',
            'member.verification.notice' => '/email/verify',
            'member.verification.send' => '/email/verification-notification',
        ];

        foreach ($names as $name => $path) {
            $this->assertSame($path, '/'.Route::getRoutes()->getByName($name)->uri());
            $this->assertSame('/en'.$path, '/'.Route::getRoutes()->getByName($name.'.en')->uri());
        }

        $this->assertSame('/login/link/abc', route('member.login.link.consume', ['token' => 'abc'], false));
        $this->assertSame('/en/reset-password/abc', route('member.password.reset.en', ['token' => 'abc'], false));
        $this->assertSame('/email/verify/1/h', route('member.verification.verify', ['id' => 1, 'hash' => 'h'], false));
    }

    public function test_guest_routes_carry_the_matching_locale_middleware(): void
    {
        $zh = Route::getRoutes()->getByName('member.login');
        $en = Route::getRoutes()->getByName('member.login.en');

        $this->assertContains('member.locale:zh_TW', $zh->gatherMiddleware());
        $this->assertContains('member.locale:en', $en->gatherMiddleware());
        $this->assertContains('guest:member', $zh->gatherMiddleware());
    }

    public function test_the_verification_link_requires_a_valid_signature(): void
    {
        $this->assertContains('signed', Route::getRoutes()->getByName('member.verification.verify')->gatherMiddleware());
    }
}

<?php

namespace Tests\Feature\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_footer_links_to_member_login_in_both_languages(): void
    {
        $this->get('/')->assertSee(route('member.login'), false)->assertSee('會員登入');
        $this->get('/en')->assertSee(route('member.login.en'), false)->assertSee('Member login');
    }

    public function test_robots_txt_blocks_member_pages(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/member', '/login', '/register', '/forgot-password', '/reset-password', '/email/verify', '/en/member', '/en/login', '/en/register'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }
    }

    public function test_member_pages_are_not_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertDontSee('/login')->assertDontSee('/register');
    }

    public function test_member_pages_are_marked_noindex(): void
    {
        foreach (['/login', '/en/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertSee('<meta name="robots" content="noindex">', false);
        }
    }
}

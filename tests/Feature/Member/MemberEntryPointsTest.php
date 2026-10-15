<?php

namespace Tests\Feature\Member;

use App\Models\Member;
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

    public function test_landing_header_shows_member_center_when_logged_in(): void
    {
        $this->actingAs(Member::factory()->create(), 'member');

        $this->get('/')->assertSee('會員頁面')->assertSee('href="'.route('member.center').'"', false);
        $this->get('/en')->assertSee('Member center')->assertSee('href="'.route('member.center.en').'"', false);
    }

    public function test_landing_header_shows_member_login_when_guest(): void
    {
        $this->get('/')->assertDontSee('會員頁面');
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

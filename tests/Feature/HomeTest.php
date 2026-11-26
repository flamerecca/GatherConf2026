<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_chinese_home_looks_forward_to_the_next_event(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('GatherConf 2026 已圓滿結束')
            ->assertSee('謝謝你，我們下次再見')
            ->assertSee('data-home-review', false)
            ->assertSee(route('landing'), false)
            ->assertSee(route('bbti'), false)
            ->assertSee(route('home.en'), false)
            ->assertSee('mailto:contact@mg.gatherconf.net', false)
            ->assertDontSee('立即報名')
            ->assertDontSee('luma.com', false);
    }

    public function test_english_home_shows_english_copy(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('GatherConf 2026 has wrapped up')
            ->assertSee('Thank you, see you next time')
            ->assertSee(route('landing.en'), false)
            ->assertSee(route('bbti.en'), false)
            ->assertSee(route('home'), false)
            ->assertDontSee('謝謝你，我們下次再見');
    }

    public function test_the_2026_event_pages_live_under_the_2026_path(): void
    {
        $this->assertSame(url('/2026'), route('landing'));
        $this->assertSame(url('/2026/en/agenda'), route('agenda.en'));
        $this->assertSame(url('/2026/volunteers'), route('volunteers'));

        $this->get('/2026')->assertOk()->assertSee('立即報名');
        $this->get('/2026/en')->assertOk();
    }

    public function test_the_old_event_urls_are_gone(): void
    {
        foreach (['/agenda', '/en/agenda', '/code-of-conduct', '/communities', '/volunteers', '/call-for-sponsor', '/en/volunteers'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_discovery_files_list_the_home_and_the_2026_pages(): void
    {
        $this->get('/sitemap.xml')->assertSee(route('home'), false)->assertSee(route('home.en'), false)->assertSee(url('/2026/agenda'), false);
        $this->get('/llms.txt')->assertSee(route('home'), false)->assertSee(url('/2026/agenda'), false);
    }
}

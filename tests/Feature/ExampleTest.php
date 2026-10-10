<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_chinese_landing_page_shows_event_details(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('共聚年會')
            ->assertSee('2026-11-21 09:00-17:00')
            ->assertSee('為什麼要舉辦共聚年會')
            ->assertSee('name="color-scheme" content="light dark"', false)
            ->assertSee('台大綜合教學館 綜201-203')
            ->assertSee('https://luma.com/wfjisb47', false)
            ->assertSee('金獎級')
            ->assertSee('銀獎級')
            ->assertSee('銅獎級')
            ->assertSee('NT$ 20,000');
    }

    public function test_english_landing_page_shows_event_details(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Our mission is to create an open, inclusive, and dynamic platform')
            ->assertSee('Why GatherConf')
            ->assertSee('Gold Tier')
            ->assertSee('Silver Tier')
            ->assertSee('Bronze Tier')
            ->assertSee('€500')
            ->assertSee('€250')
            ->assertSee('€125')
            ->assertDontSee('NT$')
            ->assertSee('https://luma.com/wfjisb47', false)
            ->assertDontSee('金獎級');
    }

    public function test_language_switch_links_to_the_other_locale(): void
    {
        $this->get('/')->assertSee(route('landing.en'), false);
        $this->get('/en')->assertSee(route('landing'), false);
    }

    public function test_code_of_conduct_pages_are_available_in_both_languages(): void
    {
        $this->get('/code-of-conduct')
            ->assertOk()
            ->assertSee('行為準則')
            ->assertSee('禁止的行為')
            ->assertSee('mailto:contact@mg.gatherconf.net', false)
            ->assertSee(route('conduct.en'), false);

        $this->get('/en/code-of-conduct')
            ->assertOk()
            ->assertSee('Prohibited Behavior')
            ->assertSee('mailto:contact@mg.gatherconf.net', false)
            ->assertSee(route('conduct'), false)
            ->assertDontSee('禁止的行為');
    }

    public function test_landing_footer_links_to_the_code_of_conduct(): void
    {
        $this->get('/')
            ->assertSee(route('conduct'), false)
            ->assertSee('data-theme-toggle', false)
            ->assertSeeInOrder(['<nav', route('conduct'), '</nav>', '<footer'], false);
        $this->get('/code-of-conduct')->assertSee('data-theme-toggle', false);
        $this->get('/en')->assertSee(route('conduct.en'), false);
    }
}

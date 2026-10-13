<?php

namespace Tests\Feature;

use Tests\TestCase;

class CallForSponsorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_chinese_page_shows_tiers_event_details_and_apply_info(): void
    {
        $this->get('/call-for-sponsor')
            ->assertOk()
            ->assertSee('邀請你成為共聚年會的贊助夥伴')
            ->assertSee('NT$ 20,000')
            ->assertSee('NT$ 10,000')
            ->assertSee('NT$ 5,000')
            ->assertSee('2026-11-21 09:00-17:00')
            ->assertSee('台大綜合教學館 綜201-203')
            ->assertSee('mailto:contact@mg.gatherconf.net', false)
            ->assertSee(route('call-for-sponsor.en'), false)
            ->assertDontSee('Become a GatherConf sponsor');
    }

    public function test_english_page_shows_english_copy(): void
    {
        $this->get('/en/call-for-sponsor')
            ->assertOk()
            ->assertSee('Become a GatherConf sponsor')
            ->assertSee('Gold Tier')
            ->assertSee(route('call-for-sponsor'), false)
            ->assertDontSee('邀請你成為共聚年會的贊助夥伴');
    }

    public function test_comparison_table_lists_every_benefit_once_with_tier_columns(): void
    {
        $page = $this->get('/call-for-sponsor')->assertOk();

        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, '<th scope="row" class="p-4 font-medium">宣傳文案內 Logo 曝光</th>'));
        $this->assertSame(1, substr_count($html, '<th scope="row" class="p-4 font-medium">閃電講 5 分鐘宣傳</th>'));
        $this->assertSame(3, substr_count($html, '<th scope="col" class="p-4 text-center text-sm font-semibold">'));
    }

    public function test_only_the_gold_tier_includes_the_lightning_talk(): void
    {
        $html = $this->get('/call-for-sponsor')->getContent();

        $row = $this->rowFor($html, '閃電講 5 分鐘宣傳');

        $this->assertSame(1, substr_count($row, 'sr-only">包含<'));
        $this->assertSame(2, substr_count($row, 'sr-only">不包含<'));
    }

    public function test_page_is_in_the_sitemap_with_both_languages(): void
    {
        $this->get('/sitemap.xml')
            ->assertSee('<loc>'.route('call-for-sponsor').'</loc>', false)
            ->assertSee('<loc>'.route('call-for-sponsor.en').'</loc>', false);
    }

    public function test_landing_page_and_footer_link_to_the_page(): void
    {
        $this->get('/')->assertSee(route('call-for-sponsor'), false);
        $this->get('/en')->assertSee(route('call-for-sponsor.en'), false);
        $this->get('/communities')->assertSee(route('call-for-sponsor'), false);
    }

    public function test_page_has_canonical_and_alternate_links(): void
    {
        $this->get('/call-for-sponsor')
            ->assertSee('<link rel="canonical" href="'.route('call-for-sponsor').'">', false)
            ->assertSee('hreflang="en" href="'.route('call-for-sponsor.en').'"', false);
    }

    private function rowFor(string $html, string $benefit): string
    {
        $start = strpos($html, '>'.$benefit.'</th>');
        $this->assertNotFalse($start, 'benefit row not found');
        $end = strpos($html, '</tr>', $start);

        return substr($html, $start, $end - $start);
    }
}

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

    public function test_communities_pages_list_partner_logos_in_both_languages(): void
    {
        $this->get('/communities')
            ->assertOk()
            ->assertSee('合作社群')
            ->assertSee('Taiwan Kotlin User Group')
            ->assertSee('https://taiwan-kotlin-user-group.github.io/', false)
            ->assertSee('網路遊民探險中')
            ->assertDontSee('Digital Rogue')
            ->assertSee('Taiwan Obsidian User Group')
            ->assertSee('https://www.meetup.com/taiwan-obsidian-user-group/', false)
            ->assertSee('GDG 桃園')
            ->assertDontSee('GDG Taoyuan')
            ->assertSee('https://gdg.community.dev/gdg-taoyuan/', false)
            ->assertSee('https://digitalrogue.world/', false)
            ->assertSee('推廣 Kotlin 程式語言的台灣社群')
            ->assertSee('開發者 Buffet')
            ->assertDontSee('Developer Buffet')
            ->assertSee('https://www.facebook.com/groups/buffet.dev', false)
            ->assertSee('iPlayground')
            ->assertSee('https://iplayground.io/', false)
            ->assertSee('人本與科技共融發展協會')
            ->assertDontSee('Orbio Community')
            ->assertSee('https://linktr.ee/orbio.community', false)
            ->assertSee('WordPress Taiwan')
            ->assertSee('https://www.facebook.com/groups/wordpresstw', false)
            ->assertSee(route('communities.en'), false);

        $this->get('/en/communities')
            ->assertOk()
            ->assertSee('Community Partners')
            ->assertSee('Taiwan Kotlin User Group')
            ->assertSee('Digital Rogue')
            ->assertDontSee('網路遊民探險中')
            ->assertSee('GDG Taoyuan')
            ->assertDontSee('GDG 桃園')
            ->assertSee('Developer Buffet')
            ->assertSee('Orbio Community')
            ->assertDontSee('人本與科技共融發展協會')
            ->assertDontSee('開發者 Buffet')
            ->assertSee(route('communities'), false)
            ->assertDontSee('合作社群');
    }

    public function test_claude_taiwan_is_listed_as_a_community_partner(): void
    {
        $this->get('/communities')
            ->assertSee('Claude Taiwan')
            ->assertSee('https://www.facebook.com/share/g/1DmSxiabtn/', false);

        $this->get('/en/communities')
            ->assertSee('Claude Taiwan')
            ->assertSee('A community for Claude users in Taiwan');
    }

    public function test_landing_images_are_served_as_responsive_avif_and_webp_with_a_png_fallback(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['community-gathering-watercolor' => [1024, 572], 'community-silos-watercolor' => [1024, 434]] as $name => [$width, $height]) {
            foreach ([360, 480, 576, 672, 768, 864, 960, 1024] as $variant) {
                $this->assertFileExists(resource_path("images/{$name}-{$variant}.webp"));
                $this->assertFileExists(resource_path("images/{$name}-{$variant}.avif"));
            }

            $this->assertFileExists(resource_path("images/{$name}.png"));
            $this->assertSame([$width, $height], array_slice(getimagesize(resource_path("images/{$name}.png")), 0, 2));
        }

        $this->assertSame(2, substr_count($html, '<source type="image/avif"'));
        $this->assertSame(2, substr_count($html, '<source type="image/webp" sizes="(min-width: 1184px) 1120px, calc(100vw - 32px)"'));
        $this->assertSame(5, substr_count($html, '480w, '));
        $this->assertSame(5, substr_count($html, '672w, '));
        $this->assertSame(5, substr_count($html, '1024w"'));
        $this->assertStringContainsString('width="1024" height="572"', $html);
        $this->assertStringContainsString('width="1024" height="434"', $html);
    }

    public function test_every_partner_logo_has_responsive_webp_variants(): void
    {
        $logos = collect(config('communities'))->map(fn (array $c) => 'community-logo/'.$c['logo'])
            ->merge(collect(config('sponsors'))->map(fn (array $s) => 'sponsor-logo/'.$s['logo']));

        foreach ($logos as $logo) {
            $base = resource_path('images/'.preg_replace('/\.[^.]+$/', '', $logo));

            foreach ([200, 400, 500] as $width) {
                $this->assertFileExists("{$base}-{$width}.webp");
            }
        }

        $html = $this->get('/communities')->assertOk()->getContent();

        $this->assertSame(count(config('communities')), preg_match_all('/200w,\s+400w,\s+500w"/', $html));
        $this->assertStringContainsString('sizes="160px"', $html);
        $this->assertStringContainsString('width="160" height="160"', $html);

        $this->assertStringContainsString('sizes="192px"', $this->get('/')->getContent());
    }

    public function test_the_site_logo_is_served_as_responsive_webp(): void
    {
        foreach ([128, 256, 448, 687] as $width) {
            $this->assertFileExists(resource_path("images/GatherConf-Logo-{$width}.webp"));
        }

        $this->assertSame(2, substr_count($this->get('/')->getContent(), '128w, '));
        $this->assertSame(2, substr_count($this->get('/agenda')->getContent(), '128w, '));
        $this->assertSame(2, substr_count($this->get('/')->getContent(), 'fetchpriority="high"'));
        $this->assertSame(1, preg_match('/<img[^>]*width="687"[^>]*>/s', $this->get('/')->getContent(), $heroLogo));
        $this->assertStringContainsString('fetchpriority="high"', $heroLogo[0]);
    }

    public function test_the_gathering_illustration_is_preloaded_with_high_priority_only_on_wide_screens(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<img[^>]*width="1024" height="572"[^>]*>/s', $html, $gathering);
        preg_match('/<img[^>]*width="1024" height="434"[^>]*>/s', $html, $silos);

        $this->assertStringContainsString('loading="eager"', $gathering[0]);
        $this->assertStringNotContainsString('fetchpriority', $gathering[0]);

        $this->assertSame(1, preg_match('/<link rel="preload" as="image" type="image\/avif" media="\(min-width: 1024px\)" fetchpriority="high"[^>]*imagesrcset="[^"]*1024w"/s', $html));
        $this->assertSame(1, substr_count($html, 'rel="preload"'));
        $this->assertStringNotContainsString('loading="eager"', $silos[0]);
        $this->assertStringContainsString('loading="lazy"', $silos[0]);
    }

    public function test_the_mascot_is_small_lazy_and_does_not_compete_with_the_hero(): void
    {
        foreach ([160, 320, 480] as $width) {
            $this->assertFileExists(resource_path("images/bunny/NVHG-{$width}.webp"));
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<img[^>]*alt="GatherConf 吉祥物 Bunny"[^>]*>/s', $html, $mascot));
        $this->assertStringContainsString('loading="lazy"', $mascot[0]);
        $this->assertStringNotContainsString('fetchpriority', $mascot[0]);
        $this->assertStringContainsString('160w, ', $html);
    }

    public function test_landing_footer_links_to_the_communities_page(): void
    {
        $this->get('/')->assertSee(route('communities'), false);
        $this->get('/en')->assertSee(route('communities.en'), false);
    }

    public function test_landing_page_shows_the_community_partners_section(): void
    {
        $this->get('/')
            ->assertSee('合作社群')
            ->assertSee('Taiwan Kotlin User Group')
            ->assertSeeInOrder(['年會概述', '合作社群', '贊助方案']);

        $this->get('/en')
            ->assertSee('Community Partners')
            ->assertSee('Taiwan Kotlin User Group')
            ->assertSeeInOrder(['Conference Overview', 'Community Partners', 'Sponsorship']);
    }

    public function test_sponsorship_tiers_and_sponsors_share_the_same_tier_border_colors(): void
    {
        $this->get('/')
            ->assertSee('border-4 border-tier-gold', false)
            ->assertSee('border-4 border-tier-silver', false)
            ->assertSee('border-4 border-tier-bronze', false);
    }

    public function test_landing_page_lists_current_sponsors_after_the_sponsorship_tiers(): void
    {
        $this->get('/')
            ->assertSeeInOrder(['贊助方案', '感謝贊助商', '金獎級', '兔給得資訊有限公司'])
            ->assertSee('size-48 rounded-full', false)
            ->assertSee('https://www.rabbit-gather.com/', false);

        $this->get('/en')
            ->assertSeeInOrder(['Sponsorship', 'Our Sponsors', 'Gold Tier', 'Rabbit Gather Co., Ltd.'])
            ->assertDontSee('兔給得資訊有限公司')
            ->assertSee('https://www.rabbit-gather.com/', false);
    }

    public function test_jetbrains_is_listed_as_a_bronze_sponsor(): void
    {
        $this->get('/')
            ->assertSeeInOrder(['感謝贊助商', '金獎級', '兔給得資訊有限公司', '銅獎級', 'JetBrains'])
            ->assertSee('size-32 rounded-full', false)
            ->assertSee('https://www.jetbrains.com/', false);

        $this->get('/en')
            ->assertSeeInOrder(['Our Sponsors', 'Gold Tier', 'Rabbit Gather Co., Ltd.', 'Bronze Tier', 'JetBrains']);
    }

    public function test_leadowl_is_listed_as_a_gold_sponsor(): void
    {
        $this->get('/')
            ->assertSeeInOrder(['金獎級', '領角鴞資訊整合有限公司', '兔給得資訊有限公司', '銅獎級', 'JetBrains'])
            ->assertSee('https://odoo.leadowl.tw/', false);

        $this->get('/en')
            ->assertSeeInOrder(['Gold Tier', 'Leadowl Information Integration Co., Ltd.', 'Rabbit Gather Co., Ltd.', 'Bronze Tier', 'JetBrains'])
            ->assertDontSee('領角鴞資訊整合有限公司');
    }

    public function test_sponsor_list_is_hidden_when_there_are_no_sponsors(): void
    {
        config(['sponsors' => []]);

        $this->get('/')->assertDontSee('感謝贊助商');
    }

    public function test_every_page_declares_canonical_alternates_and_social_metadata(): void
    {
        foreach (['landing' => 'landing', 'conduct' => 'conduct', 'communities' => 'communities'] as $name => $base) {
            $this->get(route($base))
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.route($base).'">', false)
                ->assertSee('hreflang="zh-Hant-TW" href="'.route($base).'"', false)
                ->assertSee('hreflang="en" href="'.route($base.'.en').'"', false)
                ->assertSee('hreflang="x-default" href="'.route($base).'"', false)
                ->assertSee('property="og:image" content="'.asset('og-image.jpg').'"', false)
                ->assertSee('property="og:locale" content="zh_TW"', false)
                ->assertSee('name="twitter:card" content="summary_large_image"', false);

            $this->get(route($base.'.en'))
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.route($base.'.en').'">', false)
                ->assertSee('property="og:locale" content="en_US"', false);
        }
    }

    public function test_landing_page_embeds_event_structured_data(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('application/ld+json', false);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $graph = json_decode($matches[1], true)['@graph'];
        $event = collect($graph)->firstWhere('@type', 'Event');

        $this->assertSame('2026-11-21T09:00:00+08:00', $event['startDate']);
        $this->assertSame('2026-11-21T17:00:00+08:00', $event['endDate']);
        $this->assertSame('台大綜合教學館 綜201-203', $event['location']['name']);
        $this->assertSame('https://luma.com/wfjisb47', $event['offers']['url']);

        $this->get('/communities')->assertDontSee('application/ld+json', false);
    }

    public function test_robots_and_sitemap_are_served(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('sitemap'));

        $sitemap = $this->get('/sitemap.xml')->assertOk();

        foreach (['landing', 'conduct', 'communities'] as $base) {
            $sitemap->assertSee('<loc>'.route($base).'</loc>', false)
                ->assertSee('<loc>'.route($base.'.en').'</loc>', false);
        }
    }

    public function test_favicon_and_share_assets_exist(): void
    {
        foreach (['favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'og-image.jpg', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)));
        }
    }
}

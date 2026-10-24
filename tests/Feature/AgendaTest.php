<?php

namespace Tests\Feature;

use Tests\TestCase;

class AgendaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_config_has_two_tracks_with_eight_sessions_in_total(): void
    {
        $tracks = config('agenda');

        $this->assertCount(2, $tracks);
        $this->assertSame(8, collect($tracks)->sum(fn (array $track) => count($track['sessions'])));
        $this->assertCount(8, collect($tracks)->flatMap(fn (array $track) => $track['sessions'])->pluck('id')->unique());
    }

    public function test_the_chinese_page_shows_eight_sessions_with_seven_placeholders(): void
    {
        $response = $this->get('/agenda')->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('A 軌')
            ->assertSee('B 軌')
            ->assertSee('第 4 場');

        $this->assertSame(8, substr_count($response->getContent(), 'data-session="'));
        $this->assertSame(7, substr_count($response->getContent(), '議題待公布'));
        $this->assertSame(7, substr_count($response->getContent(), '講者待公布'));
        $this->assertSame(7, substr_count($response->getContent(), '議題大綱待公布'));
    }

    public function test_the_english_page_shows_eight_sessions_with_seven_placeholders(): void
    {
        $response = $this->get('/en/agenda')->assertOk()
            ->assertSee('Track A')
            ->assertSee('Track B')
            ->assertSee('Session 4');

        $this->assertSame(7, substr_count($response->getContent(), 'Topic to be announced'));
    }

    public function test_filled_in_sessions_show_their_content_in_the_right_language(): void
    {
        config(['agenda' => [['id' => 'a', 'sessions' => [[
            'id' => 'a1',
            'date' => '2026-11-21',
            'time' => '10:00 - 10:45',
            'speaker_name' => '王小明',
            'speaker_photo' => null,
            'speaker_bio' => ['zh_TW' => '資深開發者', 'en' => 'Veteran developer'],
            'title' => ['zh_TW' => '給新手的 Kotlin', 'en' => 'Kotlin for beginners'],
            'outline' => ['zh_TW' => '從語法到實戰', 'en' => 'From syntax to practice'],
        ]]]]]);

        $this->get('/agenda')->assertSee('王小明')->assertSee('給新手的 Kotlin')->assertSee('資深開發者')->assertSee('從語法到實戰')->assertDontSee('議題待公布');
        $this->get('/en/agenda')->assertSee('Kotlin for beginners')->assertSee('Veteran developer')->assertSee('From syntax to practice');
    }

    public function test_matthias_geisler_is_scheduled_for_track_a_second_slot(): void
    {
        $session = config('agenda.0.sessions.1');

        $this->assertSame('a2', $session['id']);
        $this->assertSame('10:45 - 11:30', $session['time']);
        $this->assertSame('Matthias Geisler', $session['speaker_name']);
        $this->assertFileExists(resource_path('images/speakers/'.$session['speaker_photo']));

        $this->get('/agenda')->assertSee('Matthias Geisler')->assertSee('Beyond the Code: Building Community in the Age of AI')->assertSee('KUG Berlin');
        $this->get('/en/agenda')->assertSee('Matthias Geisler')->assertSee('Berlindroid');
    }

    public function test_the_pages_link_to_each_other_and_are_in_the_sitemap(): void
    {
        $this->get('/agenda')->assertSee(route('agenda.en'), false);
        $this->get('/en/agenda')->assertSee(route('agenda'), false);
        $this->get('/sitemap.xml')->assertSee(route('agenda'), false)->assertSee(route('agenda.en'), false);
    }

    public function test_the_footer_links_to_the_agenda(): void
    {
        $this->get('/')->assertSee(route('agenda'), false);
        $this->get('/en')->assertSee(route('agenda.en'), false);
    }

    public function test_the_landing_page_shows_the_eight_sessions_and_links_to_the_full_agenda(): void
    {
        $zh = $this->get('/')->assertOk()->assertSee('查看完整議程')->assertSee(route('agenda'), false);
        $this->assertSame(8, substr_count($zh->getContent(), 'data-landing-session="'));

        $en = $this->get('/en')->assertOk()->assertSee('View the full agenda')->assertSee(route('agenda.en'), false);
        $this->assertSame(8, substr_count($en->getContent(), 'data-landing-session="'));
    }

    public function test_filled_in_sessions_show_on_the_landing_page(): void
    {
        config(['agenda' => [['id' => 'a', 'sessions' => [[
            'id' => 'a1',
            'date' => '2026-11-21',
            'time' => '10:00 - 10:45',
            'speaker_name' => '王小明',
            'speaker_photo' => null,
            'title' => ['zh_TW' => '給新手的 Kotlin', 'en' => 'Kotlin for beginners'],
            'outline' => ['zh_TW' => null, 'en' => null],
        ]]]]]);

        $this->get('/')->assertSee('王小明')->assertSee('給新手的 Kotlin');
        $this->get('/en')->assertSee('Kotlin for beginners');
    }

    public function test_each_session_shows_its_time_on_the_agenda_and_landing_pages(): void
    {
        $slots = ['10:00 - 10:45', '10:45 - 11:30', '13:00 - 13:45', '13:45 - 14:30'];

        foreach (config('agenda') as $track) {
            $this->assertSame($slots, array_column($track['sessions'], 'time'));
        }

        foreach (['/agenda' => '2026-11-21 ', '/en/agenda' => '2026-11-21 ', '/' => '', '/en' => ''] as $path => $datePrefix) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach ($slots as $slot) {
                $this->assertSame(2, substr_count($html, '<time>'.$datePrefix.$slot.'</time>'), "{$path} {$slot}");
            }
        }
    }
}

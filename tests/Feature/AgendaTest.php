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

    public function test_the_chinese_page_shows_eight_sessions_with_two_talk_placeholders(): void
    {
        $response = $this->get('/agenda')->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('A 軌')
            ->assertSee('B 軌')
            ->assertSee('第 4 場');

        $this->assertSame(8, substr_count($response->getContent(), 'data-session="'));
        $this->assertSame(2, substr_count($response->getContent(), '議題待公布'));
        $this->assertSame(2, substr_count($response->getContent(), '講者待公布'));
        $this->assertSame(2, substr_count($response->getContent(), '議題大綱待公布'));
    }

    public function test_the_english_page_shows_eight_sessions_with_two_talk_placeholders(): void
    {
        $response = $this->get('/en/agenda')->assertOk()
            ->assertSee('Track A')
            ->assertSee('Track B')
            ->assertSee('Session 4');

        $this->assertSame(2, substr_count($response->getContent(), 'Topic to be announced'));
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

    public function test_track_b_last_slot_is_a_double_talk_session(): void
    {
        $session = config('agenda.1.sessions.3');

        $this->assertSame('b4', $session['id']);
        $this->assertSame('13:45 - 14:30', $session['time']);
        $this->assertCount(2, $session['talks']);

        foreach ($session['talks'] as $talk) {
            $this->assertFileExists(resource_path('images/speakers/'.$talk['speaker_photo']));
        }

        $html = $this->get('/agenda')->assertOk()->assertSee('講題 1')->assertSee('講題 2')
            ->assertSee('邱伊嫻 Vivian')->assertSee('不會寫程式的室內設計師，怎麼做出自己的工作系統')
            ->assertSee('楊政翰/藏鏡羊')->assertSee('一位攝影師的AI 轉型實踐')->getContent();

        $this->assertSame(9, substr_count($html, 'data-talk="'));
        $this->assertSame(1, substr_count($html, 'data-session="b4"'));
        $this->get('/en/agenda')->assertSee('Talk 1')->assertSee('Talk 2');
    }

    public function test_a_double_talk_session_shows_both_speakers_and_titles(): void
    {
        $talk = fn (string $name, string $zh, string $en) => [
            'speaker_name' => $name,
            'speaker_photo' => null,
            'speaker_bio' => ['zh_TW' => "{$name}介紹", 'en' => "{$name} bio"],
            'title' => ['zh_TW' => $zh, 'en' => $en],
            'outline' => ['zh_TW' => "{$zh}大綱", 'en' => "{$en} outline"],
        ];

        config(['agenda' => [['id' => 'b', 'sessions' => [[
            'id' => 'b4',
            'date' => '2026-11-21',
            'time' => '13:45 - 14:30',
            'talks' => [
                $talk('王小明', '第一個講題', 'First talk'),
                $talk('陳小華', '第二個講題', 'Second talk'),
            ],
        ]]]]]);

        $this->get('/agenda')->assertOk()
            ->assertSeeInOrder(['講題 1', '王小明', '王小明介紹', '第一個講題', '講題 2', '陳小華', '陳小華介紹', '第二個講題'])
            ->assertDontSee('講者待公布');
        $this->get('/en/agenda')->assertSeeInOrder(['Talk 1', 'First talk', 'Talk 2', 'Second talk']);

        $landing = $this->get('/')->assertOk()->assertSeeInOrder(['第一個講題', '王小明', '第二個講題', '陳小華'])->getContent();
        $this->assertSame(1, substr_count($landing, 'data-landing-session="'));
        $this->assertSame(2, substr_count($landing, 'data-talk'));
    }

    public function test_dash_outlines_render_as_nested_lists_and_plain_outlines_stay_paragraphs(): void
    {
        $html = $this->get('/agenda')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-outline-list'));
        $this->assertStringContainsString('<span class="font-semibold">01 為什麼設計師要自己做工具</span>', $html);
        $this->assertStringContainsString('<li>現成軟體不懂系統櫃報價，也不懂工地流程</li>', $html);
        $this->assertStringContainsString('<li>從攝影轉型為行銷整合，協助傳統產業轉型</li>', $html);
        $this->assertStringNotContainsString('- 01 ', $html);

        $this->get('/en/agenda')->assertSee('01 Why designers should build their own tools')->assertSee('The limits of AI');
    }
}

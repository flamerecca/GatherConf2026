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

    public function test_the_chinese_page_shows_eight_placeholder_sessions(): void
    {
        $response = $this->get('/agenda')->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('A 軌')
            ->assertSee('B 軌')
            ->assertSee('第 4 場');

        $this->assertSame(8, substr_count($response->getContent(), 'data-session="'));
        $this->assertSame(8, substr_count($response->getContent(), '議題待公布'));
        $this->assertSame(8, substr_count($response->getContent(), '講者待公布'));
        $this->assertSame(8, substr_count($response->getContent(), '議題大綱待公布'));
    }

    public function test_the_english_page_shows_eight_placeholder_sessions(): void
    {
        $response = $this->get('/en/agenda')->assertOk()
            ->assertSee('Track A')
            ->assertSee('Track B')
            ->assertSee('Session 4');

        $this->assertSame(8, substr_count($response->getContent(), 'Topic to be announced'));
    }

    public function test_filled_in_sessions_show_their_content_in_the_right_language(): void
    {
        config(['agenda' => [['id' => 'a', 'sessions' => [[
            'id' => 'a1',
            'speaker_name' => '王小明',
            'speaker_photo' => null,
            'title' => ['zh_TW' => '給新手的 Kotlin', 'en' => 'Kotlin for beginners'],
            'outline' => ['zh_TW' => '從語法到實戰', 'en' => 'From syntax to practice'],
        ]]]]]);

        $this->get('/agenda')->assertSee('王小明')->assertSee('給新手的 Kotlin')->assertSee('從語法到實戰')->assertDontSee('議題待公布');
        $this->get('/en/agenda')->assertSee('Kotlin for beginners')->assertSee('From syntax to practice');
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
}

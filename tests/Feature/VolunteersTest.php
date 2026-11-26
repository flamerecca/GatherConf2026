<?php

namespace Tests\Feature;

use Tests\TestCase;

class VolunteersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pages_show_the_empty_state_while_the_list_is_empty(): void
    {
        config(['volunteers' => []]);

        $this->get('/2026/volunteers')
            ->assertOk()
            ->assertSee('感謝志工')
            ->assertSee('志工名單整理中')
            ->assertSee(route('volunteers.en'), false);

        $this->get('/2026/en/volunteers')
            ->assertOk()
            ->assertSee('Thank You, Volunteers')
            ->assertSee('volunteer list is being put together')
            ->assertSee(route('volunteers'), false);
    }

    public function test_volunteers_are_grouped_by_team_in_both_languages(): void
    {
        config(['volunteers' => [
            ['name' => ['zh_TW' => '報到組', 'en' => 'Registration'], 'members' => [['name' => '小明', 'photo' => 'xiaoming.jpg'], ['name' => 'Alice']]],
            ['name' => ['zh_TW' => '場地組', 'en' => 'Venue'], 'members' => [['name' => '阿華']]],
            ['name' => ['zh_TW' => '空組', 'en' => 'Empty'], 'members' => []],
        ]]);

        $this->get('/2026/volunteers')
            ->assertOk()
            ->assertSeeInOrder(['報到組', '小明', 'Alice', '場地組', '阿華'])
            ->assertDontSee('空組')
            ->assertDontSee('志工名單整理中');

        $this->get('/2026/en/volunteers')
            ->assertOk()
            ->assertSeeInOrder(['Registration', '小明', 'Alice', 'Venue', '阿華'])
            ->assertDontSee('Empty');
    }

    public function test_volunteers_without_a_photo_use_the_fixed_bunny_image(): void
    {
        config(['volunteers' => [
            ['name' => ['zh_TW' => '報到組', 'en' => 'Registration'], 'members' => [['name' => 'Alice'], ['name' => 'Bob', 'photo' => null]]],
        ]]);

        $html = $this->get('/2026/volunteers')->assertOk()->assertSee('Alice 的照片')->getContent();

        $this->assertSame(2, substr_count($html, '的照片"'));
        $this->assertFileExists(resource_path('images/volunteers/default-bunny.svg'));
    }

    public function test_member_names_are_escaped(): void
    {
        config(['volunteers' => [
            ['name' => ['zh_TW' => '報到組', 'en' => 'Registration'], 'members' => [['name' => '<script>alert(1)</script>']]],
        ]]);

        $this->get('/2026/volunteers')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_footer_links_to_the_volunteers_page_and_discovery_files_list_it(): void
    {
        $this->get('/2026')->assertSee(route('volunteers'), false);
        $this->get('/2026/en')->assertSee(route('volunteers.en'), false);
        $this->get('/sitemap.xml')->assertSee(route('volunteers'), false)->assertSee(route('volunteers.en'), false);
        $this->get('/llms.txt')->assertSee(route('volunteers'), false);
    }
}

<?php

namespace Tests\Feature\CommunityMessages;

use Tests\TestCase;

class CommunityCardTest extends TestCase
{
    public function test_every_card_on_the_communities_page_links_to_its_message_form(): void
    {
        $response = $this->get('/communities')->assertOk();

        foreach (config('communities') as $community) {
            $response->assertSee(route('member.community-message.create', $community['slug']), false);
        }
    }

    public function test_the_english_page_links_to_the_english_form(): void
    {
        $this->get('/en/communities')
            ->assertOk()
            ->assertSee(route('member.community-message.create.en', 'gdg-taoyuan'), false);
    }

    public function test_the_landing_cards_have_no_contact_link(): void
    {
        $this->get('/')->assertOk()->assertDontSee('/member/communities/', false);
    }

    public function test_each_community_site_is_linked_exactly_once_on_the_communities_page(): void
    {
        $html = $this->get('/communities')->getContent();

        foreach (config('communities') as $community) {
            $this->assertSame(1, substr_count($html, 'href="'.$community['url'].'"'), $community['url']);
        }
    }
}

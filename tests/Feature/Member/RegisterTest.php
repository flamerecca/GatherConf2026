<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_pages_render_in_both_languages(): void
    {
        $this->get('/register')->assertOk()->assertSee('註冊會員')->assertSee('noindex', false);
        $this->get('/en/register')->assertOk()->assertSee('Sign up')->assertDontSee('註冊會員');
    }

    public function test_a_logged_in_member_cannot_open_the_registration_page(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/register')->assertRedirect('/member');
    }
}

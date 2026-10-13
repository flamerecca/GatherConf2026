<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_configured_admin_email_can_access_the_panel(): void
    {
        $admin = User::factory()->create(['email' => 'flamerecca711@gmail.com']);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_email_comparison_ignores_case(): void
    {
        $admin = User::factory()->create(['email' => 'FlameRecca711@Gmail.com']);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_any_other_admin_user_is_denied(): void
    {
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $this->assertFalse($other->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($other)->get('/admin')->assertForbidden();
    }

    public function test_a_member_with_the_admin_email_still_cannot_enter_the_panel(): void
    {
        $member = Member::factory()->create(['email' => 'flamerecca711@gmail.com']);

        $this->actingAs($member, 'member')->get('/admin')->assertRedirect();
    }

    public function test_allowed_emails_can_be_configured(): void
    {
        config(['auth.admin_emails' => ['ops@example.com']]);

        $this->assertTrue(User::factory()->create(['email' => 'ops@example.com'])->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(User::factory()->create(['email' => 'flamerecca711@gmail.com'])->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_an_empty_allow_list_denies_everyone(): void
    {
        config(['auth.admin_emails' => []]);

        $this->assertFalse(User::factory()->create(['email' => 'flamerecca711@gmail.com'])->canAccessPanel(Filament::getPanel('admin')));
    }
}

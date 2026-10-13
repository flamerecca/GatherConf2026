<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_deactivate_action_disables_an_active_member(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('deactivate')->table($member))
            ->assertNotified();

        $this->assertFalse($member->fresh()->is_active);
    }

    public function test_activate_action_enables_an_inactive_member(): void
    {
        $member = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('activate')->table($member))
            ->assertNotified();

        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_mark_verified_sets_verification_time(): void
    {
        $member = Member::factory()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('markVerified')->table($member))
            ->assertNotified();

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_actions_are_only_visible_when_applicable(): void
    {
        $active = Member::factory()->create();
        $inactive = Member::factory()->inactive()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->assertActionVisible(TestAction::make('deactivate')->table($active))
            ->assertActionHidden(TestAction::make('activate')->table($active))
            ->assertActionHidden(TestAction::make('markVerified')->table($active))
            ->assertActionHidden(TestAction::make('deactivate')->table($inactive))
            ->assertActionVisible(TestAction::make('activate')->table($inactive))
            ->assertActionVisible(TestAction::make('markVerified')->table($inactive));
    }

    public function test_view_page_exposes_the_same_actions(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('deactivate')
            ->assertNotified();

        $this->assertFalse($member->fresh()->is_active);
    }
}

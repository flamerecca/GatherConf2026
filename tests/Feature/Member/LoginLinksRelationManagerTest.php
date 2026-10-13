<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\LoginLinksRelationManager;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginLinksRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_relation_manager_renders_on_view_and_edit_pages(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertSeeLivewire(LoginLinksRelationManager::class);

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertSeeLivewire(LoginLinksRelationManager::class);
    }

    public function test_lists_only_the_members_own_links(): void
    {
        $member = Member::factory()->create();
        $own = MemberLoginLink::factory()->count(2)->for($member)->create();
        $other = MemberLoginLink::factory()->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords($own)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_token_hash_is_never_rendered(): void
    {
        $member = Member::factory()->create();
        $link = MemberLoginLink::factory()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertDontSee($link->token_hash);
    }

    public function test_revoke_action_revokes_a_usable_link(): void
    {
        $member = Member::factory()->create();
        $link = MemberLoginLink::factory()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callAction(TestAction::make('revoke')->table($link))
            ->assertNotified();

        $this->assertSame(MemberLoginLink::STATUS_REVOKED, $link->fresh()->status());
    }

    public function test_revoke_is_hidden_for_used_expired_and_revoked_links(): void
    {
        $member = Member::factory()->create();
        $used = MemberLoginLink::factory()->used()->for($member)->create();
        $expired = MemberLoginLink::factory()->expired()->for($member)->create();
        $revoked = MemberLoginLink::factory()->revoked()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertActionHidden(TestAction::make('revoke')->table($used))
            ->assertActionHidden(TestAction::make('revoke')->table($expired))
            ->assertActionHidden(TestAction::make('revoke')->table($revoked));
    }
}

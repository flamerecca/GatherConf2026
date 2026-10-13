<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_shows_members(): void
    {
        $members = Member::factory()->count(3)->create();

        Livewire::test(ListMembers::class)
            ->assertOk()
            ->assertCanSeeTableRecords($members);
    }

    public function test_list_can_search_by_name_email_and_organization(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com', 'organization' => 'Kotlin Taiwan']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com', 'organization' => 'WordPress Taiwan']);

        Livewire::test(ListMembers::class)
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob])
            ->searchTable('bob@example.com')
            ->assertCanSeeTableRecords([$bob])
            ->assertCanNotSeeTableRecords([$alice])
            ->searchTable('Kotlin')
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob]);
    }

    public function test_list_can_filter_by_active_status(): void
    {
        $active = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_email_verification(): void
    {
        $verified = Member::factory()->create();
        $unverified = Member::factory()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->filterTable('email_verified_at', true)
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$unverified])
            ->filterTable('email_verified_at', false)
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified]);
    }

    public function test_members_without_organization_render_in_list_and_view(): void
    {
        $member = Member::factory()->create(['organization' => null]);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->searchTable($member->name)
            ->assertCanSeeTableRecords([$member]);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])->assertOk();
    }

    public function test_edit_saves_name_organization_and_active_status(): void
    {
        $member = Member::factory()->create(['organization' => 'Old Org']);

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->fillForm(['name' => '新名字', 'organization' => 'New Org', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'name' => '新名字',
            'organization' => 'New Org',
            'is_active' => false,
        ]);
    }

    public function test_edit_cannot_change_email_or_password(): void
    {
        $member = Member::factory()->create(['email' => 'keep@example.com']);
        $originalHash = $member->password;

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertFormFieldDoesNotExist('password')
            ->fillForm(['name' => '改名', 'email' => 'changed@example.com'])
            ->call('save');

        $member->refresh();

        $this->assertSame('keep@example.com', $member->email);
        $this->assertSame($originalHash, $member->password);
    }

    public function test_edit_requires_a_name(): void
    {
        $member = Member::factory()->create();

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->fillForm(['name' => ''])
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_resource_has_no_create_page_and_no_delete(): void
    {
        $member = Member::factory()->create();

        $this->assertSame(['index', 'view', 'edit'], array_keys(MemberResource::getPages()));
        $this->assertFalse(MemberResource::canCreate());
        $this->assertFalse(MemberResource::canDelete($member));
        $this->assertFalse(MemberResource::canDeleteAny());
    }
}

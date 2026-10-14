<?php

namespace Tests\Feature\Messages;

use App\Filament\Resources\Friendships\FriendshipResource;
use App\Filament\Resources\Friendships\Pages\ListFriendships;
use App\Filament\Resources\Friendships\Pages\ViewFriendship;
use App\Models\Friendship;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FriendshipResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_friendships(): void
    {
        $friendships = Friendship::factory()->count(3)->create();

        Livewire::test(ListFriendships::class)
            ->assertOk()
            ->assertCanSeeTableRecords($friendships);
    }

    public function test_list_can_search_by_member_name_and_email(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com']);
        $carol = Member::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);
        $dave = Member::factory()->create(['name' => 'Dave', 'email' => 'dave@example.com']);
        $withAlice = Friendship::factory()->between($alice, $bob)->create();
        $other = Friendship::factory()->between($carol, $dave)->create();

        Livewire::test(ListFriendships::class)
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$withAlice])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('dave@example.com')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$withAlice]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $pending = Friendship::factory()->create();
        $accepted = Friendship::factory()->accepted()->create();

        Livewire::test(ListFriendships::class)
            ->filterTable('status', Friendship::STATUS_ACCEPTED)
            ->assertCanSeeTableRecords([$accepted])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_view_page_renders(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        Livewire::test(ViewFriendship::class, ['record' => $friendship->getKey()])->assertOk();
    }

    public function test_the_resource_is_read_only(): void
    {
        $friendship = Friendship::factory()->create();

        $this->assertSame(['index', 'view'], array_keys(FriendshipResource::getPages()));
        $this->assertFalse(FriendshipResource::canCreate());
        $this->assertFalse(FriendshipResource::canEdit($friendship));
        $this->assertFalse(FriendshipResource::canDelete($friendship));
        $this->assertFalse(FriendshipResource::canDeleteAny());
    }

    public function test_only_allow_listed_admins_can_open_it_over_http(): void
    {
        $this->get('/admin/friendships')->assertOk();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get('/admin/friendships')
            ->assertForbidden();

        $this->actingAs(Member::factory()->create(), 'member')
            ->get('/admin/friendships')
            ->assertRedirect();
    }
}

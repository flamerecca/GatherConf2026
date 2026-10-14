<?php

namespace Tests\Feature\Messages;

use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Models\Member;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class MessageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_messages_with_their_content(): void
    {
        $messages = Message::factory()->count(3)->create();

        Livewire::test(ListMessages::class)
            ->assertOk()
            ->assertCanSeeTableRecords($messages);
    }

    public function test_list_can_search_by_body_and_member(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com']);
        $about = Message::factory()->from($alice, $bob)->create(['body' => '關於咖啡的對話']);
        $other = Message::factory()->create(['body' => '完全無關']);

        Livewire::test(ListMessages::class)
            ->searchTable('咖啡')
            ->assertCanSeeTableRecords([$about])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('bob@example.com')
            ->assertCanSeeTableRecords([$about])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_list_can_filter_by_read_state(): void
    {
        $unread = Message::factory()->create();
        $read = Message::factory()->read()->create();

        Livewire::test(ListMessages::class)
            ->filterTable('read_at', true)
            ->assertCanSeeTableRecords([$read])
            ->assertCanNotSeeTableRecords([$unread])
            ->filterTable('read_at', false)
            ->assertCanSeeTableRecords([$unread])
            ->assertCanNotSeeTableRecords([$read]);
    }

    public function test_view_page_shows_the_full_body_and_surrounding_messages(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Message::factory()->from($a, $b)->create(['body' => '前一則']);
        $target = Message::factory()->from($b, $a)->create(['body' => str_repeat('長', 300)]);
        Message::factory()->from($a, $b)->create(['body' => '後一則']);
        Message::factory()->create(['body' => '別人的對話']);

        Livewire::test(ViewMessage::class, ['record' => $target->getKey()])
            ->assertOk()
            ->assertSee(str_repeat('長', 300))
            ->assertSee('前一則')
            ->assertSee('後一則')
            ->assertDontSee('別人的對話');
    }

    public function test_the_resource_is_read_only(): void
    {
        $message = Message::factory()->create();

        $this->assertSame(['index', 'view'], array_keys(MessageResource::getPages()));
        $this->assertFalse(MessageResource::canCreate());
        $this->assertFalse(MessageResource::canEdit($message));
        $this->assertFalse(MessageResource::canDelete($message));
        $this->assertFalse(MessageResource::canDeleteAny());
    }

    public function test_only_allow_listed_admins_can_open_it_over_http(): void
    {
        $this->get('/admin/messages')->assertOk();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get('/admin/messages')
            ->assertForbidden();

        Auth::forgetGuards();

        $this->actingAs(Member::factory()->create(), 'member')
            ->get('/admin/messages')
            ->assertRedirect();
    }
}

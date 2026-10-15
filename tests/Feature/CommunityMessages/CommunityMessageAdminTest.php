<?php

namespace Tests\Feature\CommunityMessages;

use App\Filament\Resources\CommunityMessages\CommunityMessageResource;
use App\Filament\Resources\CommunityMessages\Pages\ListCommunityMessages;
use App\Filament\Resources\CommunityMessages\Pages\ViewCommunityMessage;
use App\Models\CommunityMessage;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunityMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_messages_and_filters_by_status_and_community(): void
    {
        $pending = CommunityMessage::factory()->create();
        $closed = CommunityMessage::factory()->create(['status' => 'closed', 'community_slug' => 'iplayground', 'community_name' => 'iPlayground']);

        Livewire::test(ListCommunityMessages::class)
            ->assertCanSeeTableRecords([$pending, $closed])
            ->filterTable('status', 'closed')
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$pending])
            ->resetTableFilters()
            ->filterTable('community_slug', 'gdg-taoyuan')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_list_can_search_by_subject_body_and_member(): void
    {
        $member = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $match = CommunityMessage::factory()->for($member)->create(['subject' => '講座合作', 'body' => '咖啡']);
        $other = CommunityMessage::factory()->create(['subject' => '無關', 'body' => '其他']);

        Livewire::test(ListCommunityMessages::class)
            ->searchTable('講座')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])
            ->searchTable('alice@example.com')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_view_page_shows_member_details_and_content(): void
    {
        $member = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com', 'organization' => '某公司']);
        $message = CommunityMessage::factory()->for($member)->create(['subject' => '主旨', 'body' => '內文']);

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->assertSee('Alice Wang')->assertSee('alice@example.com')->assertSee('某公司')
            ->assertSee('主旨')->assertSee('內文')->assertSee('GDG 桃園');
    }

    public function test_status_actions_record_the_handler(): void
    {
        $message = CommunityMessage::factory()->create();

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->callAction('markForwarded')->assertNotified();
        $this->assertSame('forwarded', $message->fresh()->status);
        $this->assertSame(auth()->id(), $message->fresh()->handled_by);

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->callAction('markClosed')->assertNotified();
        $this->assertSame('closed', $message->fresh()->status);

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->callAction('reopen')->assertNotified();
        $this->assertSame('pending', $message->fresh()->status);
        $this->assertNull($message->fresh()->handled_by);
    }

    public function test_the_note_can_be_edited_but_not_the_content(): void
    {
        $message = CommunityMessage::factory()->create(['subject' => '原主旨', 'body' => '原內文']);

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->callAction('editNote', ['admin_note' => '已用 Email 轉給社群'])
            ->assertNotified();

        $fresh = $message->fresh();
        $this->assertSame('已用 Email 轉給社群', $fresh->admin_note);
        $this->assertSame('原主旨', $fresh->subject);
        $this->assertSame('原內文', $fresh->body);
    }

    public function test_the_resource_has_no_create_edit_or_delete(): void
    {
        $message = CommunityMessage::factory()->create();

        $this->assertFalse(CommunityMessageResource::canCreate());
        $this->assertFalse(CommunityMessageResource::canEdit($message));
        $this->assertFalse(CommunityMessageResource::canDelete($message));
        $this->assertFalse(CommunityMessageResource::canDeleteAny());
    }

    public function test_the_navigation_badge_counts_pending_messages(): void
    {
        CommunityMessage::factory()->count(2)->create();
        CommunityMessage::factory()->create(['status' => 'closed']);

        $this->assertSame('2', CommunityMessageResource::getNavigationBadge());
    }

    public function test_the_badge_is_hidden_when_nothing_is_pending(): void
    {
        CommunityMessage::factory()->create(['status' => 'closed']);

        $this->assertNull(CommunityMessageResource::getNavigationBadge());
    }

    public function test_the_view_page_shows_who_handled_the_message(): void
    {
        $message = CommunityMessage::factory()->create();

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->callAction('markForwarded');

        Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
            ->assertSee(auth()->user()->name);
    }
}

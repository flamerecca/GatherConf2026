<?php

namespace Tests\Feature\Bottles;

use App\Filament\Resources\DriftBottles\DriftBottleResource;
use App\Filament\Resources\DriftBottles\Pages\ListDriftBottles;
use App\Filament\Resources\DriftBottles\Pages\ViewDriftBottle;
use App\Filament\Resources\DriftBottles\RelationManagers\ReportsRelationManager;
use App\Models\DriftBottle;
use App\Models\DriftBottleReport;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DriftBottleAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_bottles_and_filters_by_pending_reports(): void
    {
        $plain = DriftBottle::factory()->create();
        $reported = DriftBottle::factory()->create();
        DriftBottleReport::factory()->create(['bottle_id' => $reported->id]);

        Livewire::test(ListDriftBottles::class)
            ->assertCanSeeTableRecords([$plain, $reported])
            ->filterTable('pending_reports', true)
            ->assertCanSeeTableRecords([$reported])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_list_can_search_by_body_and_member(): void
    {
        $sender = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $match = DriftBottle::factory()->create(['sender_id' => $sender->id, 'body' => '咖啡瓶子']);
        $other = DriftBottle::factory()->create(['body' => '無關']);

        Livewire::test(ListDriftBottles::class)
            ->searchTable('咖啡')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('alice@example.com')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_view_page_shows_sender_and_recipient_to_the_admin(): void
    {
        $sender = Member::factory()->create(['name' => 'Alice Wang']);
        $recipient = Member::factory()->create(['name' => 'Bob Lin']);
        $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'body' => '內容']);

        Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
            ->assertSee('Alice Wang')
            ->assertSee('Bob Lin')
            ->assertSee('內容');
    }

    public function test_remove_and_restore_actions(): void
    {
        $bottle = DriftBottle::factory()->create();

        Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
            ->callAction('removeBottle')
            ->assertNotified();
        $this->assertNotNull($bottle->fresh()->removed_at);

        Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
            ->callAction('restoreBottle')
            ->assertNotified();
        $this->assertNull($bottle->fresh()->removed_at);
    }

    public function test_reply_remove_and_restore_actions(): void
    {
        $bottle = DriftBottle::factory()->create(['replied_at' => now(), 'reply_body' => '回覆']);

        Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
            ->callAction('removeReply')
            ->assertNotified();
        $this->assertNotNull($bottle->fresh()->reply_removed_at);

        Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
            ->callAction('restoreReply')
            ->assertNotified();
        $this->assertNull($bottle->fresh()->reply_removed_at);
    }

    public function test_a_report_can_be_confirmed_or_dismissed(): void
    {
        $bottle = DriftBottle::factory()->create(['removed_at' => now()]);
        $confirmed = DriftBottleReport::factory()->create(['bottle_id' => $bottle->id]);
        $dismissed = DriftBottleReport::factory()->create(['bottle_id' => $bottle->id]);

        Livewire::test(ReportsRelationManager::class, ['ownerRecord' => $bottle, 'pageClass' => ViewDriftBottle::class])
            ->callAction(TestAction::make('confirmViolation')->table($confirmed))
            ->callAction(TestAction::make('dismiss')->table($dismissed));

        $this->assertSame('violation', $confirmed->fresh()->outcome);
        $this->assertNotNull($confirmed->fresh()->reviewed_at);
        $this->assertSame('dismissed', $dismissed->fresh()->outcome);
        $this->assertNull($bottle->fresh()->removed_at);
    }

    public function test_the_resource_is_read_only(): void
    {
        $this->assertFalse(DriftBottleResource::canCreate());
        $bottle = DriftBottle::factory()->create();
        $this->assertFalse(DriftBottleResource::canEdit($bottle));
        $this->assertFalse(DriftBottleResource::canDelete($bottle));
    }
}

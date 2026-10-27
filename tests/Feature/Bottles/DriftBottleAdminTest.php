<?php

namespace Tests\Feature\Bottles;

use App\Filament\Resources\DriftBottles\DriftBottleResource;
use App\Filament\Resources\DriftBottles\Pages\ListDriftBottles;
use App\Filament\Resources\DriftBottles\Pages\ViewDriftBottle;
use App\Filament\Resources\DriftBottles\RelationManagers\ReportsRelationManager;
use App\Filament\Resources\NpcCharacters\Pages\CreateNpcCharacter;
use App\Models\DriftBottle;
use App\Models\DriftBottleReport;
use App\Models\Member;
use App\Models\NpcCharacter;
use App\Models\User;
use App\Notifications\DriftBottleReceivedNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $confirmedBottle = DriftBottle::factory()->create(['removed_at' => now()]);
        $dismissedBottle = DriftBottle::factory()->create(['removed_at' => now()]);
        $confirmed = DriftBottleReport::factory()->create(['bottle_id' => $confirmedBottle->id]);
        $dismissed = DriftBottleReport::factory()->create(['bottle_id' => $dismissedBottle->id]);

        Livewire::test(ReportsRelationManager::class, ['ownerRecord' => $confirmedBottle, 'pageClass' => ViewDriftBottle::class])
            ->callAction(TestAction::make('confirmViolation')->table($confirmed));
        Livewire::test(ReportsRelationManager::class, ['ownerRecord' => $dismissedBottle, 'pageClass' => ViewDriftBottle::class])
            ->callAction(TestAction::make('dismiss')->table($dismissed));

        $this->assertSame('violation', $confirmed->fresh()->outcome);
        $this->assertNotNull($confirmed->fresh()->reviewed_at);
        $this->assertNotNull($confirmedBottle->fresh()->removed_at);
        $this->assertSame('dismissed', $dismissed->fresh()->outcome);
        $this->assertNull($dismissedBottle->fresh()->removed_at);
    }

    public function test_the_resource_is_read_only(): void
    {
        $this->assertFalse(DriftBottleResource::canCreate());
        $bottle = DriftBottle::factory()->create();
        $this->assertFalse(DriftBottleResource::canEdit($bottle));
        $this->assertFalse(DriftBottleResource::canDelete($bottle));
    }

    public function test_admin_can_throw_a_system_bottle_to_a_random_member(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        Livewire::test(ListDriftBottles::class)
            ->callAction('throwSystemBottle', ['body' => '歡迎來到 Gather'])
            ->assertNotified();

        $bottle = DriftBottle::query()->sole();
        $this->assertNull($bottle->sender_id);
        $this->assertSame($member->getKey(), $bottle->recipient_id);
        $this->assertTrue($bottle->isFromSystem());
        Notification::assertSentTo($member, DriftBottleReceivedNotification::class);
    }

    public function test_system_bottle_is_not_kept_when_nobody_is_eligible(): void
    {
        Notification::fake();

        Livewire::test(ListDriftBottles::class)
            ->callAction('throwSystemBottle', ['body' => '沒有人收得到'])
            ->assertNotified();

        $this->assertSame(0, DriftBottle::query()->count());
        Notification::assertNothingSent();
    }

    public function test_system_bottle_is_never_released_or_picked_and_reply_does_not_notify(): void
    {
        Notification::fake();
        $member = Member::factory()->create();
        $bottle = DriftBottle::throwFromSystem('hi');
        $bottle->forceFill(['assigned_at' => now()->subDays(2)])->save();

        $this->artisan('drift-bottles:release')->assertSuccessful();
        $this->assertSame($member->getKey(), $bottle->fresh()->recipient_id);

        $bottle->open($member);
        $this->assertTrue($bottle->fresh()->replyFrom($member, '謝謝'));
    }

    public function test_system_bottle_sent_as_npc_uses_the_bound_member_as_sender(): void
    {
        Notification::fake();
        $recipient = Member::factory()->create();
        $npc = NpcCharacter::factory()->create(['name' => '森林長老']);

        Livewire::test(ListDriftBottles::class)
            ->callAction('throwSystemBottle', ['npc_character_id' => $npc->getKey(), 'body' => '孩子，歡迎'])
            ->assertNotified();

        $bottle = DriftBottle::query()->sole();
        $this->assertSame($npc->member_id, $bottle->sender_id);
        $this->assertSame($npc->getKey(), $bottle->npc_character_id);
        $this->assertSame($recipient->getKey(), $bottle->recipient_id);
        $this->assertTrue($bottle->isFromSystem());

        $this->actingAs($recipient, 'member')
            ->get(route('member.bottles.show', $bottle))
            ->assertOk()
            ->assertSee('森林長老');
    }

    public function test_npc_bottle_is_never_released_and_never_sent_to_its_own_member(): void
    {
        Notification::fake();
        $npc = NpcCharacter::factory()->create();
        $bottle = DriftBottle::throwFromSystem('hi', $npc);
        $this->assertNull($bottle);

        $member = Member::factory()->create();
        $bottle = DriftBottle::throwFromSystem('hi', $npc);
        $bottle->forceFill(['assigned_at' => now()->subDays(2)])->save();

        $this->artisan('drift-bottles:release')->assertSuccessful();
        $this->assertSame($member->getKey(), $bottle->fresh()->recipient_id);
    }

    public function test_npc_character_resource_can_be_created(): void
    {
        $member = Member::factory()->create();

        Livewire::test(CreateNpcCharacter::class)
            ->fillForm(['name' => '森林長老', 'member_id' => $member->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('npc_characters', ['name' => '森林長老', 'member_id' => $member->getKey()]);
    }
}

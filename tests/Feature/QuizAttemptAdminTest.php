<?php

namespace Tests\Feature;

use App\Filament\Resources\QuizAttempts\Pages\ListQuizAttempts;
use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use App\Models\QuizAttempt;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuizAttemptAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_the_list_shows_guest_and_member_results_and_filters_guests(): void
    {
        $guest = QuizAttempt::factory()->create();
        $bound = QuizAttempt::factory()->forMember()->create();

        Livewire::test(ListQuizAttempts::class)
            ->assertCanSeeTableRecords([$guest, $bound])
            ->filterTable('bound', false)
            ->assertCanSeeTableRecords([$guest])
            ->assertCanNotSeeTableRecords([$bound])
            ->filterTable('bound', true)
            ->assertCanSeeTableRecords([$bound])
            ->assertCanNotSeeTableRecords([$guest]);
    }

    public function test_clearing_guest_results_keeps_member_results_and_recent_ones(): void
    {
        $oldGuest = QuizAttempt::factory()->create(['completed_at' => now()->subDays(40)]);
        $recentGuest = QuizAttempt::factory()->create(['completed_at' => now()->subDays(2)]);
        $oldMember = QuizAttempt::factory()->forMember()->create(['completed_at' => now()->subDays(40)]);

        Livewire::test(ListQuizAttempts::class)
            ->callAction('clearGuestAttempts', ['before' => now()->subDays(30)->toDateString()])
            ->assertNotified('已清除 1 筆訪客測驗');

        $this->assertSoftDeleted($oldGuest);
        $this->assertNotSoftDeleted($recentGuest);
        $this->assertNotSoftDeleted($oldMember);
        $this->get(route('quiz.result', $oldGuest->uuid))->assertNotFound();
    }

    public function test_clearing_removes_the_unique_bunny_files(): void
    {
        Storage::fake('public');
        config(['filesystems.unique_bunny_disk' => 'public']);
        Storage::disk('public')->put('unique-bunnies/a.webp', 'x');
        Storage::disk('public')->put('unique-bunnies/b.webp', 'x');
        QuizAttempt::factory()->create(['completed_at' => now()->subDays(40), 'unique_bunny_path' => 'unique-bunnies/a.webp']);
        QuizAttempt::factory()->forMember()->create(['completed_at' => now()->subDays(40), 'unique_bunny_path' => 'unique-bunnies/b.webp']);

        Livewire::test(ListQuizAttempts::class)
            ->callAction('clearGuestAttempts', ['before' => now()->toDateString()]);

        Storage::disk('public')->assertMissing('unique-bunnies/a.webp');
        Storage::disk('public')->assertExists('unique-bunnies/b.webp');
    }

    public function test_the_date_cannot_be_in_the_future(): void
    {
        $attempt = QuizAttempt::factory()->create();

        Livewire::test(ListQuizAttempts::class)
            ->callAction('clearGuestAttempts', ['before' => now()->addDays(3)->toDateString()])
            ->assertHasActionErrors(['before']);

        $this->assertNotSoftDeleted($attempt);
    }

    public function test_a_single_result_and_a_batch_can_be_deleted(): void
    {
        $one = QuizAttempt::factory()->create();
        $two = QuizAttempt::factory()->create();
        $three = QuizAttempt::factory()->create();

        Livewire::test(ListQuizAttempts::class)
            ->callAction(TestAction::make('delete')->table($one))
            ->callTableBulkAction('delete', [$two, $three]);

        $this->assertSoftDeleted($one);
        $this->assertSoftDeleted($two);
        $this->assertSoftDeleted($three);
    }

    public function test_the_resource_cannot_create_or_edit_results(): void
    {
        $this->assertFalse(QuizAttemptResource::canCreate());
        $this->assertFalse(QuizAttemptResource::canEdit(QuizAttempt::factory()->create()));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\PersonalityTypes\Pages\EditPersonalityType;
use App\Filament\Resources\PersonalityTypes\Pages\ListPersonalityTypes;
use App\Filament\Resources\PersonalityTypes\PersonalityTypeResource;
use App\Filament\Resources\QuizQuestions\Pages\CreateQuizQuestion;
use App\Filament\Resources\QuizQuestions\Pages\EditQuizQuestion;
use App\Filament\Resources\QuizQuestions\Pages\ListQuizQuestions;
use App\Models\PersonalityType;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuizAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    private function question(array $overrides = []): QuizQuestion
    {
        return QuizQuestion::query()->create($overrides + ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '題目', 'order' => 1, 'is_active' => true]);
    }

    public function test_a_question_can_be_created_with_a_pole_that_matches_its_axis(): void
    {
        Livewire::test(CreateQuizQuestion::class)
            ->fillForm(['axis' => 'HA', 'positive_pole' => 'A', 'text' => '新題目', 'order' => 5, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('quiz_questions', ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '新題目', 'order' => 5]);
    }

    public function test_a_pole_from_another_axis_is_rejected(): void
    {
        Livewire::test(CreateQuizQuestion::class)
            ->fillForm(['axis' => 'SV', 'positive_pole' => 'H', 'text' => '錯邊', 'order' => 1])
            ->call('create')
            ->assertHasFormErrors(['positive_pole']);

        $this->assertSame(0, QuizQuestion::count());
    }

    public function test_changing_the_axis_clears_the_chosen_pole(): void
    {
        Livewire::test(CreateQuizQuestion::class)
            ->fillForm(['axis' => 'SV', 'positive_pole' => 'S'])
            ->fillForm(['axis' => 'GD'])
            ->assertFormSet(['positive_pole' => null]);
    }

    public function test_the_next_question_defaults_to_the_end_of_the_order(): void
    {
        $this->question(['order' => 7]);

        Livewire::test(CreateQuizQuestion::class)->assertFormSet(['order' => 8, 'is_active' => true]);
    }

    public function test_a_question_can_be_disabled_from_the_edit_page(): void
    {
        $question = $this->question();

        Livewire::test(EditQuizQuestion::class, ['record' => $question->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($question->fresh()->is_active);
    }

    public function test_questions_are_listed_by_order_and_can_be_filtered_by_axis(): void
    {
        $first = $this->question(['text' => '第一題', 'order' => 1]);
        $second = $this->question(['text' => '第二題', 'order' => 2, 'axis' => 'NM', 'positive_pole' => 'N']);

        Livewire::test(ListQuizQuestions::class)
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->filterTable('axis', 'NM')
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first]);
    }

    public function test_question_deletion_is_not_offered(): void
    {
        $question = $this->question();

        Livewire::test(EditQuizQuestion::class, ['record' => $question->id])->assertActionDoesNotExist('delete');
    }

    public function test_a_personality_type_can_be_edited_but_not_its_code(): void
    {
        $type = PersonalityType::query()->create(['code' => 'NSHG', 'name' => '舊名', 'summary' => '舊摘要', 'description' => '舊說明', 'strengths' => ['一'], 'weaknesses' => null]);

        Livewire::test(EditPersonalityType::class, ['record' => $type->id])
            ->fillForm([
                'code' => 'XXXX', 'name' => '新名', 'summary' => '新摘要', 'description' => '新說明',
                'strengths' => [['item' => '優勢甲'], ['item' => '優勢乙']], 'weaknesses' => [['item' => '待成長甲']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $type->refresh();
        $this->assertSame('NSHG', $type->code);
        $this->assertSame('新名', $type->name);
        $this->assertSame(['優勢甲', '優勢乙'], $type->strengths);
        $this->assertSame(['待成長甲'], $type->weaknesses);
    }

    public function test_personality_types_are_listed_without_create_or_delete(): void
    {
        $type = PersonalityType::query()->create(['code' => 'NSHG', 'name' => '類型', 'summary' => '摘要', 'description' => '說明']);

        $this->assertFalse(PersonalityTypeResource::canCreate());

        Livewire::test(ListPersonalityTypes::class)->assertCanSeeTableRecords([$type]);
        Livewire::test(EditPersonalityType::class, ['record' => $type->id])->assertActionDoesNotExist('delete');
    }
}

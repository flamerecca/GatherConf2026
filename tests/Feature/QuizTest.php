<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private function asMember(Member $member): static
    {
        Auth::forgetGuards();

        return $this->actingAs($member, 'member');
    }

    private function seedQuiz(): void
    {
        foreach (['NM' => ['N', 'M'], 'SV' => ['S', 'V'], 'HA' => ['H', 'A'], 'GD' => ['G', 'D']] as $axis => [$first, $second]) {
            foreach ([$first, $second] as $pole) {
                QuizQuestion::query()->create(['axis' => $axis, 'positive_pole' => $pole, 'text' => "題目 $axis $pole", 'order' => QuizQuestion::count() + 1, 'is_active' => true]);
            }
        }

        foreach (['NSHG', 'MVAD', 'NVAD'] as $code) {
            PersonalityType::query()->create([
                'code' => $code, 'name' => "類型$code", 'summary' => "摘要$code", 'description' => "說明$code",
                'strengths' => ['擅長一'], 'weaknesses' => ['成長一'],
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function answersOf(int $value): array
    {
        return QuizQuestion::query()->pluck('id')->mapWithKeys(fn (int $id) => [$id => $value])->all();
    }

    public function test_the_intro_page_is_public_in_both_languages(): void
    {
        $this->get(route('bbti'))->assertOk()->assertSee(__('quiz.intro.start'), false)->assertSee(route('quiz'), false);

        $this->get(route('bbti.en'))->assertOk()->assertSee('Start the quiz', false)->assertSee(route('quiz.en'), false);
    }

    public function test_guests_are_sent_to_login_before_the_quiz(): void
    {
        $this->seedQuiz();

        $this->get(route('quiz'))->assertRedirect();
        $this->post(route('quiz.store'), ['answers' => $this->answersOf(5)])->assertRedirect();
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_unverified_members_cannot_take_the_quiz(): void
    {
        $this->seedQuiz();

        $this->asMember(Member::factory()->unverified()->create())->get(route('quiz'))->assertRedirect();
    }

    public function test_the_quiz_lists_only_active_questions_in_order(): void
    {
        $this->seedQuiz();
        QuizQuestion::query()->where('text', '題目 NM N')->update(['order' => 99]);
        QuizQuestion::query()->where('text', '題目 NM M')->update(['is_active' => false]);

        $this->asMember(Member::factory()->create())->get(route('quiz'))
            ->assertOk()
            ->assertDontSee('題目 NM M')
            ->assertSeeInOrder(['題目 SV S', '題目 SV V', '題目 NM N'])
            ->assertSee('name="answers['.QuizQuestion::where('text', '題目 SV S')->value('id').']"', false);
    }

    public function test_the_quiz_shows_a_notice_when_there_are_no_questions(): void
    {
        $this->asMember(Member::factory()->create())->get(route('quiz'))->assertOk()->assertSee('data-quiz-empty', false);
    }

    public function test_submitting_stores_the_attempt_and_redirects_to_the_result(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();

        $response = $this->asMember($member)->post(route('quiz.store'), ['answers' => $this->answersOf(3)]);

        $attempt = QuizAttempt::query()->sole();
        $response->assertRedirect(route('quiz.result', $attempt->uuid));
        $this->assertSame($member->id, $attempt->user_id);
        $this->assertSame('NVAD', $attempt->type_code);
        $this->assertSame(['NM' => 0, 'SV' => 0, 'HA' => 0, 'GD' => 0], $attempt->scores);
        $this->assertTrue(Str::isUuid($attempt->uuid));
    }

    public function test_the_english_submission_redirects_to_the_english_result(): void
    {
        $this->seedQuiz();

        $response = $this->asMember(Member::factory()->create())->post(route('quiz.store.en'), ['answers' => $this->answersOf(3)]);

        $response->assertRedirect(route('quiz.result.en', QuizAttempt::query()->sole()->uuid));
    }

    public function test_the_first_completion_awards_carrots_once(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();

        $this->asMember($member)->post(route('quiz.store'), ['answers' => $this->answersOf(3)]);
        $this->asMember($member)->post(route('quiz.store'), ['answers' => $this->answersOf(3)]);

        $this->assertSame(2, QuizAttempt::count());
        $this->assertSame(config('carrots.site.complete_quiz'), $member->fresh()->carrots);
    }

    public function test_missing_or_invalid_answers_are_rejected(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $partial = $this->answersOf(3);
        array_shift($partial);

        $this->asMember($member)->post(route('quiz.store'), ['answers' => $partial])->assertSessionHasErrors();
        $this->asMember($member)->post(route('quiz.store'), ['answers' => array_map(fn () => 6, $this->answersOf(3))])->assertSessionHasErrors();
        $this->asMember($member)->post(route('quiz.store'), [])->assertSessionHasErrors('answers');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_submitting_without_questions_is_a_404(): void
    {
        $this->asMember(Member::factory()->create())->post(route('quiz.store'), ['answers' => [1 => 3]])->assertNotFound();
    }

    public function test_the_result_page_is_public_and_shows_the_type(): void
    {
        $this->seedQuiz();
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => null,
            'scores' => ['NM' => 2, 'SV' => -4, 'HA' => -2, 'GD' => 0], 'type_code' => 'NVAD', 'completed_at' => now(),
        ]);

        $this->get(route('quiz.result', $attempt->uuid))
            ->assertOk()
            ->assertSee('類型NVAD')
            ->assertSee('摘要NVAD')
            ->assertSee('說明NVAD')
            ->assertSee('擅長一')
            ->assertSee('成長一')
            ->assertSee('data-axis="SV" data-percent="100" data-leaning="V"', false)
            ->assertSee('data-axis="GD" data-percent="0" data-leaning="D"', false);

        $this->get(route('quiz.result.en', $attempt->uuid))->assertOk()->assertSee('Your bunny type');
    }

    public function test_an_unknown_result_is_a_404(): void
    {
        $this->get(route('quiz.result', (string) Str::uuid()))->assertNotFound();
    }

    public function test_bbti_is_in_the_sitemap_and_quiz_is_disallowed_in_robots(): void
    {
        $this->get(route('sitemap'))->assertSee(route('bbti'), false);
        $this->get(route('robots'))->assertSee('Disallow: /quiz');
    }
}

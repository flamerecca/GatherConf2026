<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Database\Seeders\PersonalityTypeSeeder;
use Database\Seeders\QuizQuestionSeeder;
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

    public function test_the_intro_page_states_the_number_of_active_questions(): void
    {
        $this->seedQuiz();
        QuizQuestion::query()->where('text', '題目 NM M')->update(['is_active' => false]);

        $this->get(route('bbti'))->assertSee('回答 7 題，約需 2 分鐘', false);
        $this->get(route('bbti.en'))->assertSee('Answer 7 questions in about 2 minutes', false);
    }

    public function test_the_seeded_questions_are_balanced_across_axes_and_poles(): void
    {
        $this->seed(QuizQuestionSeeder::class);

        $this->assertSame(40, QuizQuestion::count());

        foreach (['NM' => ['N', 'M'], 'SV' => ['S', 'V'], 'HA' => ['H', 'A'], 'GD' => ['G', 'D']] as $axis => $poles) {
            $this->assertSame(10, QuizQuestion::where('axis', $axis)->count(), $axis);

            foreach ($poles as $pole) {
                $this->assertSame(5, QuizQuestion::where('axis', $axis)->where('positive_pole', $pole)->count(), $axis.$pole);
            }
        }

        $this->assertSame(40, QuizQuestion::distinct('text')->count('text'));
    }

    public function test_guests_can_take_the_quiz_and_get_an_unbound_report(): void
    {
        $this->seedQuiz();

        $this->get(route('quiz'))->assertOk();
        $response = $this->post(route('quiz.store'), ['answers' => $this->answersOf(5)]);

        $attempt = QuizAttempt::query()->sole();
        $this->assertNull($attempt->user_id);
        $response->assertRedirect(route('quiz.result', $attempt->uuid));
    }

    public function test_unverified_members_get_an_unbound_report(): void
    {
        $this->seedQuiz();

        $this->asMember(Member::factory()->unverified()->create())->post(route('quiz.store'), ['answers' => $this->answersOf(5)]);

        $this->assertNull(QuizAttempt::query()->sole()->user_id);
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

    public function test_submitting_fails_cleanly_when_the_resulting_type_is_not_seeded(): void
    {
        $this->seedQuiz();
        PersonalityType::query()->delete();

        $this->asMember(Member::factory()->create())->post(route('quiz.store'), ['answers' => $this->answersOf(3)])->assertStatus(503);

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_seeders_can_run_twice_without_duplicating_or_overwriting_edits(): void
    {
        $this->seed([PersonalityTypeSeeder::class, QuizQuestionSeeder::class]);
        PersonalityType::query()->where('code', 'NSHG')->update(['name' => '後台改過']);
        QuizQuestion::query()->first()->update(['is_active' => false]);
        QuizQuestion::query()->orderByDesc('id')->first()->delete();

        $this->seed([PersonalityTypeSeeder::class, QuizQuestionSeeder::class]);

        $this->assertSame(16, PersonalityType::count());
        $this->assertSame('後台改過', PersonalityType::where('code', 'NSHG')->value('name'));
        $this->assertSame(40, QuizQuestion::withTrashed()->count());
        $this->assertFalse(QuizQuestion::query()->orderBy('id')->first()->is_active);
    }

    private function attemptFor(?Member $member, string $code, string $completedAt): QuizAttempt
    {
        return QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $member?->id,
            'scores' => ['NM' => 1, 'SV' => 1, 'HA' => 1, 'GD' => 1], 'type_code' => $code, 'completed_at' => $completedAt,
        ]);
    }

    public function test_every_question_starts_on_the_neutral_answer(): void
    {
        $this->seedQuiz();

        $html = $this->asMember(Member::factory()->create())->get(route('quiz'))->assertOk()->getContent();

        $this->assertSame(QuizQuestion::count(), preg_match_all('/<input type="radio" name="answers\[\d+\]" value="3"\s+class="accent-moss" checked>/', $html));
        $this->assertSame(0, preg_match_all('/value="[1245]"\s+class="accent-moss" checked/', $html));
    }

    public function test_each_question_is_announced_with_its_radio_group_for_screen_readers(): void
    {
        $this->seedQuiz();

        $html = $this->asMember(Member::factory()->create())->get(route('quiz'))->assertOk()->getContent();

        foreach (QuizQuestion::all() as $question) {
            $this->assertStringContainsString('aria-describedby="question-'.$question->id.'"', $html);
            $this->assertStringContainsString('<p id="question-'.$question->id.'"', $html);
        }
    }

    public function test_the_member_center_remembers_the_latest_result(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $this->attemptFor($member, 'NSHG', now()->subDays(3)->toDateTimeString());
        $latest = $this->attemptFor($member, 'MVAD', now()->subDay()->toDateTimeString());
        $this->attemptFor(Member::factory()->create(), 'NVAD', now()->toDateTimeString());

        $this->asMember($member)->get(route('member.center'))
            ->assertOk()
            ->assertSee('data-last-bbti="MVAD"', false)
            ->assertSee('類型MVAD')
            ->assertSee(route('quiz.result', $latest->uuid), false)
            ->assertSee(now()->subDay()->format('Y-m-d'))
            ->assertDontSee('data-last-bbti="NSHG"', false)
            ->assertDontSee('data-last-bbti="NVAD"', false);

        $this->asMember($member)->get(route('member.center.en'))
            ->assertSee(route('quiz.result.en', $latest->uuid), false)
            ->assertSee('Take it again');
    }

    public function test_the_member_center_shows_the_bunny_thumbnail_of_the_latest_result(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $this->attemptFor($member, 'NSHG', now()->subDay()->toDateTimeString());
        $this->attemptFor($member, 'MVAD', now()->toDateTimeString());

        $html = $this->asMember($member)->get(route('member.center'))
            ->assertOk()
            ->assertSee('data-bunny-thumbnail="MVAD"', false)
            ->assertDontSee('data-bunny-thumbnail="NSHG"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-bunny-thumbnail="MVAD"/', $html);
        $this->assertStringContainsString('MVAD-160', $html);
        $this->assertStringContainsString('MVAD-320', $html);
    }

    public function test_every_bunny_has_thumbnails_for_the_member_center(): void
    {
        $this->seed(PersonalityTypeSeeder::class);

        foreach (PersonalityType::all() as $type) {
            $this->assertSame([160, 320], array_slice(array_keys($type->bunnyThumbnailPaths()), 0, 2), $type->code);
        }
    }

    public function test_the_member_center_offers_the_quiz_when_there_is_no_result_yet(): void
    {
        $this->seedQuiz();
        $this->attemptFor(null, 'NSHG', now()->toDateTimeString());

        $this->asMember(Member::factory()->create())->get(route('member.center'))
            ->assertOk()
            ->assertSee('data-take-bbti', false)
            ->assertDontSee('data-last-bbti', false);
    }

    public function test_a_deleted_result_is_no_longer_remembered(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $this->attemptFor($member, 'NSHG', now()->subDay()->toDateTimeString());
        $this->attemptFor($member, 'MVAD', now()->toDateTimeString())->delete();

        $this->asMember($member)->get(route('member.center'))->assertSee('data-last-bbti="NSHG"', false);
    }

    public function test_the_intro_shows_the_last_result_to_a_member_who_has_one(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $attempt = $this->attemptFor($member, 'NSHG', now()->toDateTimeString());

        $this->asMember($member)->get(route('bbti'))
            ->assertOk()
            ->assertSee('data-last-result="NSHG"', false)
            ->assertSee(route('quiz.result', $attempt->uuid), false)
            ->assertSee('重新測驗');

        $this->asMember($member)->get(route('bbti.en'))->assertSee('Your last BBTI result')->assertSee('Take it again');
    }

    public function test_the_intro_shows_no_last_result_to_guests_or_members_without_one(): void
    {
        $this->seedQuiz();
        $this->attemptFor(null, 'NSHG', now()->toDateTimeString());

        $this->get(route('bbti'))->assertOk()->assertDontSee('data-last-result', false)->assertSee('開始測驗');

        $this->asMember(Member::factory()->create())->get(route('bbti'))
            ->assertDontSee('data-last-result', false)
            ->assertSee('開始測驗');
    }

    public function test_submitting_makes_the_new_result_the_remembered_one(): void
    {
        $this->seedQuiz();
        $member = Member::factory()->create();
        $this->attemptFor($member, 'NSHG', now()->subDay()->toDateTimeString());

        $this->asMember($member)->post(route('quiz.store'), ['answers' => $this->answersOf(3)]);

        $this->assertSame('NVAD', $member->fresh()->latestQuizAttempt->type_code);
        $this->assertSame(2, $member->quizAttempts()->count());
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

    public function test_every_one_of_the_sixteen_types_has_a_bunny_image(): void
    {
        $this->seed(PersonalityTypeSeeder::class);

        $this->assertSame(16, PersonalityType::count());

        foreach (PersonalityType::all() as $type) {
            $this->assertNotNull($type->bunnyImagePath(), $type->code.' has no bunny image');
            $this->assertFileExists(base_path($type->bunnyImagePath()));
        }
    }

    public function test_the_result_page_shows_the_bunny_for_the_type(): void
    {
        $this->seed(PersonalityTypeSeeder::class);
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => null,
            'scores' => ['NM' => 2, 'SV' => 2, 'HA' => 2, 'GD' => 2], 'type_code' => 'NSHG', 'completed_at' => now(),
        ]);

        $this->get(route('quiz.result', $attempt->uuid))
            ->assertOk()
            ->assertSee('data-bunny="NSHG"', false)
            ->assertSee('/NSHG-', false)
            ->assertSee('NSHG 拓荒工匠的創客兔插圖', false);

        $this->get(route('quiz.result.en', $attempt->uuid))->assertSee('Bunny illustration of NSHG', false);
    }

    public function test_a_type_without_an_image_still_renders_the_result(): void
    {
        $this->seedQuiz();
        PersonalityType::query()->create(['code' => 'ZZZZ', 'name' => '無圖', 'summary' => '摘要', 'description' => '說明']);
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => null, 'scores' => [], 'type_code' => 'ZZZZ', 'completed_at' => now(),
        ]);

        $this->get(route('quiz.result', $attempt->uuid))->assertOk()->assertDontSee('data-bunny', false);
    }

    public function test_result_bars_grow_toward_the_preferred_pole_label(): void
    {
        $this->seedQuiz();
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => null,
            'scores' => ['NM' => 2, 'SV' => -4, 'HA' => -2, 'GD' => 0], 'type_code' => 'NVAD', 'completed_at' => now(),
        ]);

        $html = $this->get(route('quiz.result', $attempt->uuid))->assertOk()->getContent();

        // The positive pole label is on the left and the negative one on the right.
        $this->assertMatchesRegularExpression('/data-bar-side="left"[^>]*style="width: 25%; right: 50%/', $html);
        $this->assertMatchesRegularExpression('/data-bar-side="right"[^>]*style="width: 50%; left: 50%/', $html);
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

<?php

namespace Tests\Feature;

use App\Actions\BuildUniqueBunnyTraits;
use App\Jobs\GenerateUniqueBunny;
use App\Models\Member;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Database\Seeders\PersonalityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniqueBunnyTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(array $scores = ['NM' => 8, 'SV' => -6, 'HA' => 3, 'GD' => 0], string $completedAt = 'now'): QuizAttempt
    {
        $this->seed(PersonalityTypeSeeder::class);

        return QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => null, 'scores' => $scores,
            'type_code' => 'NVHG', 'completed_at' => $completedAt,
        ]);
    }

    private function fakeGemini(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('fake-png')]]]]]],
        ])]);
    }

    public function test_traits_follow_the_scores_and_stay_the_same_for_the_same_attempt(): void
    {
        $traits = (new BuildUniqueBunnyTraits)(['NM' => 8, 'SV' => -6, 'HA' => 3, 'GD' => 0], 'uuid-1');
        $text = implode('|', $traits);

        $this->assertStringContainsString('prominently carries a tiny glowing prototype gadget', $text);
        $this->assertStringContainsString('prominently carries a paintbrush', $text);
        $this->assertStringContainsString('subtle hint of work gloves', $text);
        $this->assertStringNotContainsString('basket', $text);
        $this->assertStringNotContainsString('lantern', $text);
        $this->assertSame($traits, (new BuildUniqueBunnyTraits)(['NM' => 8, 'SV' => -6, 'HA' => 3, 'GD' => 0], 'uuid-1'));
    }

    public function test_different_attempts_of_the_same_scores_get_different_touches(): void
    {
        $scores = ['NM' => 5, 'SV' => 5, 'HA' => 5, 'GD' => 5];
        $variants = collect(range(1, 30))->map(fn (int $i): string => implode('|', (new BuildUniqueBunnyTraits)($scores, "uuid-$i")))->unique();

        $this->assertGreaterThan(5, $variants->count());
    }

    public function test_the_job_asks_gemini_with_the_type_bunny_and_stores_the_drawing_as_png_when_it_cannot_convert_it(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $this->fakeGemini();
        $attempt = $this->attempt();

        GenerateUniqueBunny::dispatchSync($attempt);

        Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'secret-key')
            && str_contains($request->url(), config('services.gemini.image_model').':generateContent')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'prominently carries a tiny glowing prototype gadget')
            && $request['contents'][0]['parts'][1]['inline_data']['data'] !== '');

        $attempt->refresh();
        $this->assertSame('unique-bunnies/'.$attempt->uuid.'.png', $attempt->unique_bunny_path);
        $this->assertNotEmpty($attempt->unique_bunny_traits);
        Storage::disk('public')->assertExists($attempt->unique_bunny_path);
        $this->assertSame('fake-png', Storage::disk('public')->get($attempt->unique_bunny_path));
    }

    public function test_the_drawing_is_saved_as_a_smaller_webp(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $canvas = imagecreatetruecolor(800, 800);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 240, 200, 120));
        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        unset($canvas);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($png)]]]]]]])]);
        $attempt = $this->attempt();

        GenerateUniqueBunny::dispatchSync($attempt);

        $path = $attempt->fresh()->unique_bunny_path;
        $this->assertSame('unique-bunnies/'.$attempt->uuid.'.webp', $path);
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame('image/webp', $size['mime']);
        $this->assertSame(768, $size[0]);
        $this->assertLessThan(strlen($png), strlen(Storage::disk('public')->get($path)));
    }

    public function test_the_job_does_nothing_without_a_key_or_when_a_drawing_exists(): void
    {
        Storage::fake('public');
        Http::fake();
        $attempt = $this->attempt();

        GenerateUniqueBunny::dispatchSync($attempt);
        Http::assertNothingSent();

        config(['services.gemini.key' => 'secret-key']);
        $attempt->update(['unique_bunny_path' => 'unique-bunnies/existing.png']);
        GenerateUniqueBunny::dispatchSync($attempt);
        Http::assertNothingSent();
    }

    public function test_a_response_without_an_image_fails_the_job_and_keeps_the_type_bunny(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'sorry']]]]]])]);
        $attempt = $this->attempt();

        $this->expectException(\RuntimeException::class);

        try {
            GenerateUniqueBunny::dispatchSync($attempt);
        } finally {
            $this->assertNull($attempt->fresh()->unique_bunny_path);
        }
    }

    /**
     * @param  array<string, int>|null  $counts  null makes the limb check itself fail
     */
    private function fakeGeminiWithLimbCheck(?array $counts): void
    {
        Http::fake([
            '*gemini-2.5-flash-image:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('fake-png')]]]]]],
            ]),
            '*gemini-2.5-flash:generateContent' => $counts === null
                ? Http::response(['error' => 'boom'], 500)
                : Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($counts)]]]]]]),
        ]);
    }

    public function test_the_prompt_demands_two_arms_two_legs_and_a_clearly_different_bunny(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $this->fakeGeminiWithLimbCheck(['bunnies' => 1, 'arms' => 2, 'hands' => 2, 'legs' => 2, 'feet' => 2]);

        GenerateUniqueBunny::dispatchSync($this->attempt());

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-2.5-flash-image')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'exactly two arms and two hands and exactly two legs')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'No third arm')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'clearly different'));
    }

    public function test_a_drawing_with_exactly_two_arms_and_two_legs_is_kept(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $this->fakeGeminiWithLimbCheck(['bunnies' => 1, 'arms' => 2, 'hands' => 2, 'legs' => 2, 'feet' => 2]);
        $attempt = $this->attempt();

        GenerateUniqueBunny::dispatchSync($attempt);

        Storage::disk('public')->assertExists($attempt->fresh()->unique_bunny_path);
    }

    public function test_a_drawing_with_extra_limbs_is_rejected_and_not_stored(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $attempt = $this->attempt();

        foreach ([['arms' => 3, 'legs' => 2], ['arms' => 2, 'legs' => 4], ['arms' => 2, 'legs' => 2, 'hands' => 3], ['arms' => 2, 'legs' => 2, 'bunnies' => 2]] as $counts) {
            $this->fakeGeminiWithLimbCheck($counts);

            try {
                GenerateUniqueBunny::dispatchSync($attempt);
                $this->fail('A bunny with '.json_encode($counts).' should be rejected.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('two arms and two legs', $exception->getMessage());
            }

            $this->assertNull($attempt->fresh()->unique_bunny_path);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_the_drawing_is_kept_when_the_limb_check_itself_fails(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        Storage::fake('public');
        $this->fakeGeminiWithLimbCheck(null);
        $attempt = $this->attempt();

        GenerateUniqueBunny::dispatchSync($attempt);

        $this->assertNotNull($attempt->fresh()->unique_bunny_path);
    }

    public function test_submitting_the_quiz_queues_the_drawing_only_when_a_key_is_configured(): void
    {
        Queue::fake();
        $this->seed(PersonalityTypeSeeder::class);

        foreach (['NM' => ['N', 'M'], 'SV' => ['S', 'V'], 'HA' => ['H', 'A'], 'GD' => ['G', 'D']] as $axis => [$first, $second]) {
            QuizQuestion::query()->create(['axis' => $axis, 'positive_pole' => $first, 'text' => "題目 $axis", 'order' => QuizQuestion::count() + 1, 'is_active' => true]);
        }

        $answers = QuizQuestion::query()->pluck('id')->mapWithKeys(fn (int $id) => [$id => 5])->all();

        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->post(route('quiz.store'), ['answers' => $answers])->assertRedirect();
        Queue::assertNothingPushed();

        config(['services.gemini.key' => 'secret-key']);
        $this->actingAs($member, 'member')->post(route('quiz.store'), ['answers' => $answers])->assertRedirect();
        Queue::assertPushed(GenerateUniqueBunny::class, 1);
    }

    public function test_the_result_page_shows_the_unique_bunny_once_it_exists(): void
    {
        Storage::fake('public');
        $attempt = $this->attempt();
        $attempt->update(['unique_bunny_path' => 'unique-bunnies/'.$attempt->uuid.'.png']);

        $this->get(route('quiz.result', $attempt->uuid))
            ->assertOk()
            ->assertSee('data-unique-bunny', false)
            ->assertSee('unique-bunnies/'.$attempt->uuid.'.png', false)
            ->assertSee('專屬創客兔')
            ->assertSee('data-type-bunny="NVHG"', false)
            ->assertDontSee('data-awaiting-unique-bunny', false);
    }

    public function test_the_unique_bunny_is_the_large_picture_and_the_type_bunny_a_small_note_beside_the_type(): void
    {
        Storage::fake('public');
        $attempt = $this->attempt();
        $attempt->update(['unique_bunny_path' => 'unique-bunnies/'.$attempt->uuid.'.webp']);

        $html = $this->get(route('quiz.result', $attempt->uuid))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-unique-bunny'));
        $this->assertSame(1, substr_count($html, 'data-type-bunny='));
        $this->assertLessThan(strpos($html, 'data-type-bunny='), strpos($html, 'data-unique-bunny'));
        $this->assertLessThan(strpos($html, 'data-type-code='), strpos($html, 'data-type-bunny='));
        $this->assertStringContainsString('NVHG-160', $html);
    }

    public function test_the_result_page_shows_the_type_bunny_while_the_drawing_is_pending(): void
    {
        config(['services.gemini.key' => 'secret-key']);
        $attempt = $this->attempt();

        $this->get(route('quiz.result', $attempt->uuid))
            ->assertOk()
            ->assertSee('data-awaiting-unique-bunny', false)
            ->assertSee('繪製中')
            ->assertSee('data-bunny="NVHG"', false)
            ->assertDontSee('data-type-bunny', false)
            ->assertDontSee('data-unique-bunny', false);
    }

    public function test_the_result_page_stops_waiting_for_an_old_attempt_or_when_not_configured(): void
    {
        $attempt = $this->attempt();
        $this->get(route('quiz.result', $attempt->uuid))->assertOk()->assertDontSee('data-awaiting-unique-bunny', false);

        config(['services.gemini.key' => 'secret-key']);
        $old = $this->attempt(completedAt: now()->subMinutes(10)->toDateTimeString());
        $this->get(route('quiz.result', $old->uuid))->assertOk()->assertDontSee('data-awaiting-unique-bunny', false)->assertSee('data-bunny="NVHG"', false);
    }
}

<?php

namespace App\Http\Controllers\Member;

use App\Actions\ComputePersonalityType;
use App\Jobs\GenerateUniqueBunny;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QuizController extends MemberController
{
    public function intro(): View
    {
        $count = $this->activeQuestions()->count();

        return view('quiz.intro', [
            'count' => $count,
            'minutes' => max(1, (int) ceil($count * 9 / 60)),
            'lastAttempt' => $this->guard()->user()?->latestQuizAttempt()->with('personalityType')->first(),
        ]);
    }

    public function show(): View
    {
        return view('quiz.show', ['questions' => $this->activeQuestions()]);
    }

    public function store(Request $request, ComputePersonalityType $compute): RedirectResponse
    {
        $questions = $this->activeQuestions();

        abort_if($questions->isEmpty(), 404);

        $rules = ['answers' => ['required', 'array']];

        foreach ($questions as $question) {
            $rules['answers.'.$question->id] = ['required', 'integer', 'between:1,5'];
        }

        $validated = $request->validate($rules, [], ['answers' => __('quiz.show.answers')]);

        $result = $compute($questions, $validated['answers']);

        abort_unless(PersonalityType::query()->where('code', $result['code'])->exists(), 503);

        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $this->guard()->id(),
            'scores' => $result['scores'],
            'type_code' => $result['code'],
            'completed_at' => now(),
        ]);

        if (filled(config('services.gemini.key'))) {
            GenerateUniqueBunny::dispatch($attempt);
        }

        return redirect($this->localizedRoute('quiz.result', ['attempt' => $attempt->uuid]));
    }

    public function result(QuizAttempt $attempt): View
    {
        $attempt->load('personalityType');

        abort_if($attempt->personalityType === null, 404);

        $totals = $this->activeQuestions()->countBy('axis');

        $bars = collect(ComputePersonalityType::AXES)->map(function (array $poles, string $axis) use ($attempt, $totals): array {
            $score = (int) ($attempt->scores[$axis] ?? 0);
            $max = max(2, 2 * ($totals[$axis] ?? 0), abs($score));

            return [
                'axis' => $axis,
                'positive' => $poles['positive'],
                'negative' => $poles['negative'],
                'score' => $score,
                'percent' => (int) round(abs($score) / $max * 100),
                'leaning' => $score > 0 ? $poles['positive'] : ($score < 0 ? $poles['negative'] : $poles['tie']),
            ];
        });

        return view('quiz.result', [
            'attempt' => $attempt,
            'type' => $attempt->personalityType,
            'bars' => $bars,
        ]);
    }

    /**
     * Stream the unique bunny of an attempt from its disk, so the bucket itself can stay private.
     */
    public function bunny(QuizAttempt $attempt): Response
    {
        abort_if($attempt->unique_bunny_path === null, 404);

        $disk = Storage::disk(config('filesystems.unique_bunny_disk'));

        abort_unless($disk->exists($attempt->unique_bunny_path), 404);

        return $disk->response($attempt->unique_bunny_path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    private function activeQuestions()
    {
        return QuizQuestion::query()->where('is_active', true)->orderBy('order')->orderBy('id')->get();
    }
}

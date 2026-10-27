<?php

namespace App\Http\Controllers\Member;

use App\Actions\ComputePersonalityType;
use App\Actions\TrackResultReferral;
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
        $count = min(QuizQuestion::PER_QUIZ, $this->activeQuestions()->count());

        return view('quiz.intro', [
            'count' => $count,
            'minutes' => max(1, (int) ceil($count * 9 / 60)),
            'lastAttempt' => $this->guard()->user()?->latestQuizAttempt()->with('personalityType')->first(),
        ]);
    }

    public function show(Request $request): View
    {
        $questions = $this->drawnQuestions($request);

        if ($questions === null) {
            $questions = QuizQuestion::draw();

            $request->session()->put(QuizQuestion::SESSION_KEY, $questions->pluck('id')->all());
        }

        return view('quiz.show', ['questions' => $questions]);
    }

    public function store(Request $request, ComputePersonalityType $compute, TrackResultReferral $referral): RedirectResponse
    {
        $questions = $this->drawnQuestions($request);

        if ($questions === null) {
            abort_if($this->activeQuestions()->isEmpty(), 404);

            return redirect($this->localizedRoute('quiz'));
        }

        $rules = ['answers' => ['required', 'array']];

        foreach ($questions as $question) {
            $rules['answers.'.$question->id] = ['required', 'integer', 'between:1,5'];
        }

        $validated = $request->validate($rules, [], ['answers' => __('quiz.show.answers')]);

        $result = $compute($questions, $validated['answers']);

        abort_unless(PersonalityType::query()->where('code', $result['code'])->exists(), 503);

        $member = $this->guard()->user();

        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $member?->email_verified_at === null ? null : $member->id,
            'scores' => $result['scores'],
            'max_scores' => array_map(
                fn (int $count): int => 2 * $count,
                array_replace(array_fill_keys(array_keys(ComputePersonalityType::AXES), 0), $questions->countBy('axis')->all()),
            ),
            'type_code' => $result['code'],
            'completed_at' => now(),
        ]);

        $request->session()->forget(QuizQuestion::SESSION_KEY);

        $referral->completed($request, $attempt);

        if (filled(config('services.gemini.key'))) {
            GenerateUniqueBunny::dispatch($attempt);
        }

        return redirect($this->localizedRoute('quiz.result', ['attempt' => $attempt->uuid]));
    }

    public function result(Request $request, QuizAttempt $attempt, TrackResultReferral $referral): View
    {
        $attempt->load('personalityType');

        abort_if($attempt->personalityType === null, 404);

        $referral->visit($request, $attempt);

        $totals = $this->activeQuestions()->countBy('axis');

        $bars = collect(ComputePersonalityType::AXES)->map(function (array $poles, string $axis) use ($attempt, $totals): array {
            $score = (int) ($attempt->scores[$axis] ?? 0);
            $axisMax = $attempt->max_scores[$axis] ?? 2 * ($totals[$axis] ?? 0);
            $max = max(2, $axisMax, abs($score));

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
    public function bunny(Request $request, QuizAttempt $attempt): Response
    {
        abort_if($attempt->unique_bunny_path === null, 404);

        $disk = Storage::disk(config('filesystems.unique_bunny_disk'));

        abort_unless($disk->exists($attempt->unique_bunny_path), 404);

        if ($request->boolean('og')) {
            return $this->socialCard((string) $disk->get($attempt->unique_bunny_path));
        }

        return $disk->response($attempt->unique_bunny_path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Put the bunny on a 1200x630 JPEG card, since social platforms crop square or webp previews badly.
     */
    private function socialCard(string $contents): Response
    {
        $bunny = imagecreatefromstring($contents);
        abort_if($bunny === false, 404);

        $card = imagecreatetruecolor(1200, 630);
        imagefill($card, 0, 0, imagecolorallocate($card, 244, 239, 225));
        imagecopyresampled($card, $bunny, 285, 0, 0, 0, 630, 630, imagesx($bunny), imagesy($bunny));

        ob_start();
        imagejpeg($card, null, 88);

        return response((string) ob_get_clean(), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * The questions drawn for this visitor's current quiz, or null when none was drawn or one of them is gone.
     *
     * @return Collection<int, QuizQuestion>|null
     */
    private function drawnQuestions(Request $request): ?Collection
    {
        $ids = $request->session()->get(QuizQuestion::SESSION_KEY);

        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $questions = $this->activeQuestions()->whereIn('id', $ids)->values();

        return $questions->count() === count($ids) ? $questions : null;
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    private function activeQuestions()
    {
        return QuizQuestion::query()->where('is_active', true)->orderBy('order')->orderBy('id')->get();
    }
}

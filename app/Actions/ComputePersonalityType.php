<?php

namespace App\Actions;

use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

class ComputePersonalityType
{
    /**
     * Axes in code order, each with its positive pole and its tie-break pole.
     *
     * @var array<string, array{positive: string, negative: string, tie: string}>
     */
    public const AXES = [
        'NM' => ['positive' => 'N', 'negative' => 'M', 'tie' => 'N'],
        'SV' => ['positive' => 'S', 'negative' => 'V', 'tie' => 'V'],
        'HA' => ['positive' => 'H', 'negative' => 'A', 'tie' => 'A'],
        'GD' => ['positive' => 'G', 'negative' => 'D', 'tie' => 'D'],
    ];

    /**
     * Turn 1-5 answers into per-axis scores and the four letter type code.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @param  array<int|string, int|string>  $answers  question id => 1..5
     * @return array{scores: array<string, int>, code: string}
     */
    public function __invoke(Collection $questions, array $answers): array
    {
        $scores = array_fill_keys(array_keys(self::AXES), 0);

        foreach ($questions as $question) {
            if (! isset($answers[$question->id]) || ! isset(self::AXES[$question->axis])) {
                continue;
            }

            $value = max(1, min(5, (int) $answers[$question->id])) - 3;

            $scores[$question->axis] += $question->positive_pole === self::AXES[$question->axis]['positive']
                ? $value
                : -$value;
        }

        $code = '';

        foreach (self::AXES as $axis => $poles) {
            $code .= match (true) {
                $scores[$axis] > 0 => $poles['positive'],
                $scores[$axis] < 0 => $poles['negative'],
                default => $poles['tie'],
            };
        }

        return ['scores' => $scores, 'code' => $code];
    }
}

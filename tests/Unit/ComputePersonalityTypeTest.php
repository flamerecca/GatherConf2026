<?php

namespace Tests\Unit;

use App\Actions\ComputePersonalityType;
use App\Models\QuizQuestion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComputePersonalityTypeTest extends TestCase
{
    private function question(int $id, string $axis, string $pole): QuizQuestion
    {
        return (new QuizQuestion)->forceFill(['id' => $id, 'axis' => $axis, 'positive_pole' => $pole]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: string, 4: int}>
     */
    public static function singleAnswers(): array
    {
        return [
            'agree with a positive pole question' => ['NM', 'N', 5, 'N', 2],
            'disagree with a positive pole question' => ['NM', 'N', 1, 'M', -2],
            'agree with a negative pole question' => ['SV', 'V', 5, 'V', -2],
            'disagree with a negative pole question' => ['SV', 'V', 1, 'S', 2],
            'mild agreement' => ['HA', 'H', 4, 'H', 1],
            'mild disagreement' => ['GD', 'G', 2, 'D', -1],
        ];
    }

    #[DataProvider('singleAnswers')]
    public function test_a_single_answer_moves_its_axis(string $axis, string $pole, int $value, string $letter, int $score): void
    {
        $result = (new ComputePersonalityType)(collect([$this->question(1, $axis, $pole)]), [1 => $value]);

        $this->assertSame($score, $result['scores'][$axis]);
        $this->assertStringContainsString($letter, $result['code']);
    }

    public function test_neutral_answers_fall_back_to_the_tie_break_letters(): void
    {
        $questions = collect([
            $this->question(1, 'NM', 'N'),
            $this->question(2, 'SV', 'S'),
            $this->question(3, 'HA', 'H'),
            $this->question(4, 'GD', 'G'),
        ]);

        $result = (new ComputePersonalityType)($questions, [1 => 3, 2 => 3, 3 => 3, 4 => 3]);

        $this->assertSame('NVAD', $result['code']);
        $this->assertSame(['NM' => 0, 'SV' => 0, 'HA' => 0, 'GD' => 0], $result['scores']);
    }

    public function test_the_code_follows_nm_sv_ha_gd_order(): void
    {
        $questions = collect([
            $this->question(1, 'NM', 'M'),
            $this->question(2, 'SV', 'S'),
            $this->question(3, 'HA', 'H'),
            $this->question(4, 'GD', 'D'),
        ]);

        $result = (new ComputePersonalityType)($questions, [1 => 5, 2 => 5, 3 => 5, 4 => 5]);

        $this->assertSame('MSHD', $result['code']);
    }

    public function test_answers_on_one_axis_add_up_across_both_poles(): void
    {
        $questions = collect([
            $this->question(1, 'SV', 'S'),
            $this->question(2, 'SV', 'S'),
            $this->question(3, 'SV', 'V'),
        ]);

        $result = (new ComputePersonalityType)($questions, [1 => 5, 2 => 4, 3 => 2]);

        $this->assertSame(2 + 1 + 1, $result['scores']['SV']);
        $this->assertSame('S', $result['code'][1]);
    }

    public function test_unanswered_questions_are_ignored_and_out_of_range_values_are_clamped(): void
    {
        $questions = collect([$this->question(1, 'NM', 'N'), $this->question(2, 'NM', 'N')]);

        $result = (new ComputePersonalityType)($questions, [1 => 99]);

        $this->assertSame(2, $result['scores']['NM']);
    }
}

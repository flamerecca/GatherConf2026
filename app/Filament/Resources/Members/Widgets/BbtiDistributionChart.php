<?php

namespace App\Filament\Resources\Members\Widgets;

use App\Models\Member;
use App\Models\PersonalityType;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class BbtiDistributionChart extends ChartWidget
{
    protected ?string $heading = 'BBTI 分布';

    protected ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): string|Htmlable|null
    {
        return '以每位會員最近一次完成的測驗結果統計，共 '.$this->completedMemberCount().' 位會員。';
    }

    /**
     * @return array<string, int>
     */
    private function countsByTypeCode(): array
    {
        return Member::query()
            ->withAggregate('latestQuizAttempt', 'type_code')
            ->get()
            ->whereNotNull('latest_quiz_attempt_type_code')
            ->countBy('latest_quiz_attempt_type_code')
            ->all();
    }

    private function completedMemberCount(): int
    {
        return array_sum($this->countsByTypeCode());
    }

    /**
     * @return array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    protected function getData(): array
    {
        $counts = $this->countsByTypeCode();
        $types = PersonalityType::query()->orderBy('code')->get(['code', 'name']);

        return [
            'datasets' => [
                [
                    'label' => '會員數',
                    'data' => $types->map(fn (PersonalityType $type): int => $counts[$type->code] ?? 0)->all(),
                ],
            ],
            'labels' => $types->map(fn (PersonalityType $type): string => $type->code.' '.$type->name)->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['precision' => 0]]],
        ];
    }
}

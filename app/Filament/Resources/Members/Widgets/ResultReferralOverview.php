<?php

namespace App\Filament\Resources\Members\Widgets;

use App\Models\ResultReferral;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResultReferralOverview extends StatsOverviewWidget
{
    protected ?string $heading = '結果頁導流';

    protected ?string $description = '以瀏覽器 Cookie 計算，每位訪客只算第一次點進的他人結果頁。含報告擁有者尚未綁定會員的導流。';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('從結果頁進來', ResultReferral::query()->count()),
            Stat::make('完成測驗', ResultReferral::query()->whereNotNull('completed_at')->count()),
            Stat::make('註冊成會員', ResultReferral::query()->whereNotNull('registered_at')->count()),
        ];
    }
}

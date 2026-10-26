<?php

namespace App\Filament\Resources\Members\Widgets;

use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ResultReferralTable extends TableWidget
{
    protected static ?string $heading = '各會員結果頁的導流成效';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->whereHas('receivedReferrals')
                    ->withCount([
                        'receivedReferrals as visitors_count',
                        'receivedReferrals as completed_count' => fn ($query) => $query->whereNotNull('result_referrals.completed_at'),
                        'receivedReferrals as registered_count' => fn ($query) => $query->whereNotNull('result_referrals.registered_at'),
                    ])
            )
            ->columns([
                TextColumn::make('name')->label('會員')->description(fn (Member $record): string => $record->email)->searchable(),
                TextColumn::make('visitors_count')->label('進來人數')->sortable(),
                TextColumn::make('completed_count')->label('完成測驗')->sortable(),
                TextColumn::make('registered_count')->label('註冊成會員')->sortable(),
            ])
            ->defaultSort('visitors_count', 'desc')
            ->emptyStateHeading('還沒有人從會員的結果頁進來');
    }
}

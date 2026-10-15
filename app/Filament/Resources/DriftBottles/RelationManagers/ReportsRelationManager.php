<?php

namespace App\Filament\Resources\DriftBottles\RelationManagers;

use App\Models\DriftBottle;
use App\Models\DriftBottleReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static ?string $title = '檢舉紀錄';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('reporter.name')->label('檢舉人'),
                TextColumn::make('target')
                    ->label('對象')
                    ->formatStateUsing(fn (string $state): string => $state === DriftBottle::TARGET_REPLY ? '回覆' : '瓶子'),
                TextColumn::make('reason')->label('原因')->placeholder('未填寫'),
                TextColumn::make('outcome')
                    ->label('處理結果')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        DriftBottleReport::OUTCOME_VIOLATION => '確認違規',
                        DriftBottleReport::OUTCOME_DISMISSED => '已駁回',
                        default => '待處理',
                    })
                    ->badge(),
                TextColumn::make('reviewed_at')->label('處理時間')->dateTime()->placeholder('尚未處理'),
                TextColumn::make('created_at')->label('檢舉時間')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('confirmViolation')
                    ->label('確認違規')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (DriftBottleReport $record): bool => $record->isPending())
                    ->action(function (DriftBottleReport $record): void {
                        $record->review(DriftBottleReport::OUTCOME_VIOLATION, auth()->id());

                        Notification::make()->title('已確認違規，內容維持下架')->success()->send();
                    }),
                Action::make('dismiss')
                    ->label('駁回並還原')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (DriftBottleReport $record): bool => $record->isPending())
                    ->action(function (DriftBottleReport $record): void {
                        $record->review(DriftBottleReport::OUTCOME_DISMISSED, auth()->id());

                        Notification::make()->title('已駁回檢舉並還原內容')->success()->send();
                    }),
            ]);
    }
}

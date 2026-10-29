<?php

namespace App\Filament\Resources\CarrotTasks\Tables;

use App\Models\CarrotLedger;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CarrotTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'transactions as completed_count' => fn ($q) => $q->where('reason', CarrotLedger::REASON_TASK),
                'transactions as revoked_count' => fn ($q) => $q->where('reason', CarrotLedger::REASON_TASK_REVOKED),
            ]))
            ->columns([
                TextColumn::make('name')->label('任務名稱')->searchable()->sortable(),
                TextColumn::make('reward')->label('獎勵')->sortable(),
                TextColumn::make('short_code')->label('短碼')->copyable()->fontFamily('mono'),
                IconColumn::make('is_active')->label('啟用')->boolean(),
                TextColumn::make('starts_at')->label('開始時間')->dateTime()->placeholder('不限'),
                TextColumn::make('ends_at')->label('結束時間')->dateTime()->placeholder('不限'),
                TextColumn::make('completed_count')
                    ->label('完成人次')
                    ->state(fn ($record): int => $record->completed_count - $record->revoked_count),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}

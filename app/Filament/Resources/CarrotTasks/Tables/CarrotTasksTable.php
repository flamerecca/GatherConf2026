<?php

namespace App\Filament\Resources\CarrotTasks\Tables;

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
            ->modifyQueryUsing(fn ($query) => $query->withCount('transactions'))
            ->columns([
                TextColumn::make('name')->label('任務名稱')->searchable()->sortable(),
                TextColumn::make('reward')->label('獎勵')->sortable(),
                IconColumn::make('is_active')->label('啟用')->boolean(),
                TextColumn::make('starts_at')->label('開始時間')->dateTime()->placeholder('不限'),
                TextColumn::make('ends_at')->label('結束時間')->dateTime()->placeholder('不限'),
                TextColumn::make('transactions_count')->label('完成人次'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}

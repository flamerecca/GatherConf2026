<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CarrotTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'carrotTransactions';

    protected static ?string $title = '胡蘿蔔明細';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')->label('時間')->dateTime()->sortable(),
                TextColumn::make('amount')
                    ->label('數量')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state),
                TextColumn::make('reason')->label('原因')->badge(),
                TextColumn::make('note')->label('說明')->placeholder('無'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

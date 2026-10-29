<?php

namespace App\Filament\Resources\AuthorizationCodes\Tables;

use App\Models\AuthorizationCode;
use App\Models\CarrotLedger;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthorizationCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'transactions as uses_count' => fn ($q) => $q->where('reason', CarrotLedger::REASON_CODE),
            ]))
            ->columns([
                TextColumn::make('name')->label('名稱')->searchable()->sortable(),
                TextColumn::make('code')->label('授權碼')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('reward')->label('獎勵')->sortable(),
                TextColumn::make('uses')
                    ->label('已兌換')
                    ->state(fn (AuthorizationCode $record): string => $record->max_uses === null
                        ? (string) $record->uses_count
                        : "{$record->uses_count} / {$record->max_uses}"),
                IconColumn::make('is_active')->label('啟用')->boolean(),
                TextColumn::make('starts_at')->label('開始時間')->dateTime()->placeholder('不限'),
                TextColumn::make('ends_at')->label('結束時間')->dateTime()->placeholder('不限'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

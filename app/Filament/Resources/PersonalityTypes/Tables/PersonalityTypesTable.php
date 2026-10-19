<?php

namespace App\Filament\Resources\PersonalityTypes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PersonalityTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('代碼')->badge()->searchable()->sortable(),
                TextColumn::make('name')->label('名稱')->searchable(),
                TextColumn::make('summary')->label('摘要')->limit(40)->tooltip(fn ($record): string => $record->summary),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

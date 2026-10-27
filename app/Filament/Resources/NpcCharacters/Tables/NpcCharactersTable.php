<?php

namespace App\Filament\Resources\NpcCharacters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NpcCharactersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('角色名稱')->searchable(),
                TextColumn::make('member.name')->label('綁定會員'),
                TextColumn::make('member.email')->label('會員 Email'),
                TextColumn::make('bottles_count')->label('已發送瓶數')->counts('bottles'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\NpcCharacters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NpcCharacterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('角色名稱')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Select::make('member_id')
                ->label('綁定會員')
                ->helperText('系統漂流瓶選擇此角色時，會以這位會員作為寄件者，會員的回覆通知也會寄給他。')
                ->relationship('member', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name}（{$record->email}）")
                ->searchable(['name', 'email'])
                ->preload()
                ->required(),
        ]);
    }
}

<?php

namespace App\Filament\Resources\PersonalityTypes\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PersonalityTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('代碼')
                ->helperText('16 種類型是固定的，代碼不可修改，否則測驗結果會找不到對應類型。')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('name')
                ->label('名稱')
                ->required()
                ->maxLength(255),
            TextInput::make('summary')
                ->label('摘要')
                ->helperText('一句話摘要，建議 20 到 40 字。')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('完整說明')
                ->required()
                ->rows(8)
                ->columnSpanFull(),
            Repeater::make('strengths')
                ->label('優勢')
                ->simple(TextInput::make('item')->required()->maxLength(255))
                ->addActionLabel('新增一項'),
            Repeater::make('weaknesses')
                ->label('待成長')
                ->simple(TextInput::make('item')->required()->maxLength(255))
                ->addActionLabel('新增一項'),
        ]);
    }
}

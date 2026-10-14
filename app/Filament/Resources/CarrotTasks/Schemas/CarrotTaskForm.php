<?php

namespace App\Filament\Resources\CarrotTasks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CarrotTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('任務名稱')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('說明')
                ->columnSpanFull(),
            TextInput::make('reward')
                ->label('獎勵胡蘿蔔數量')
                ->required()
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100000),
            Toggle::make('is_active')
                ->label('啟用')
                ->default(true),
            DateTimePicker::make('starts_at')
                ->label('開始時間'),
            DateTimePicker::make('ends_at')
                ->label('結束時間'),
        ]);
    }
}

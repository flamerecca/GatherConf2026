<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('姓名')
                ->required()
                ->maxLength(255),
            TextInput::make('organization')
                ->label('所屬社群或公司')
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('啟用'),
            TextInput::make('email')
                ->label('Email')
                ->disabled()
                ->dehydrated(false),
            DateTimePicker::make('email_verified_at')
                ->label('Email 驗證時間')
                ->placeholder('尚未驗證')
                ->disabled()
                ->dehydrated(false),
            DateTimePicker::make('created_at')
                ->label('註冊時間')
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}

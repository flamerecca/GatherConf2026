<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('姓名'),
            TextEntry::make('email')->label('Email'),
            TextEntry::make('organization')
                ->label('所屬社群或公司')
                ->placeholder('未填寫'),
            IconEntry::make('is_active')
                ->label('啟用')
                ->boolean(),
            TextEntry::make('email_verified_at')
                ->label('Email 驗證時間')
                ->dateTime()
                ->placeholder('尚未驗證'),
            TextEntry::make('created_at')
                ->label('註冊時間')
                ->dateTime(),
        ]);
    }
}

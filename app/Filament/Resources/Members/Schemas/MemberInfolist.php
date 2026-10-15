<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('暱稱'),
            TextEntry::make('email')->label('Email'),
            TextEntry::make('organization')
                ->label('所屬社群或公司')
                ->placeholder('未填寫'),
            IconEntry::make('is_active')
                ->label('啟用')
                ->boolean(),
            IconEntry::make('has_password')
                ->label('已設定密碼')
                ->state(fn (Member $record): bool => $record->hasPassword())
                ->boolean(),
            TextEntry::make('carrots')
                ->label('胡蘿蔔')
                ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)
                ->weight(fn (int $state): ?FontWeight => $state < 0 ? FontWeight::Bold : null),
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

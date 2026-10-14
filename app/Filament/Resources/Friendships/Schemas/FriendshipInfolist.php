<?php

namespace App\Filament\Resources\Friendships\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FriendshipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('low.name')->label('會員甲'),
            TextEntry::make('low.email')->label('會員甲 Email'),
            TextEntry::make('high.name')->label('會員乙'),
            TextEntry::make('high.email')->label('會員乙 Email'),
            TextEntry::make('requester.name')->label('邀請者'),
            TextEntry::make('status')->label('狀態'),
            TextEntry::make('created_at')->label('建立時間')->dateTime(),
            TextEntry::make('responded_at')->label('回應時間')->dateTime()->placeholder('尚未回應'),
        ]);
    }
}

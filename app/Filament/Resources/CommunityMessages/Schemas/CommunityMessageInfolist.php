<?php

namespace App\Filament\Resources\CommunityMessages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CommunityMessageInfolist
{
    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'pending' => '待處理',
            'forwarded' => '已轉交',
            'closed' => '已結案',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('member.name')->label('寄件會員'),
            TextEntry::make('member.email')->label('會員 Email'),
            TextEntry::make('member.organization')->label('所屬社群或公司')->placeholder('未填寫'),
            TextEntry::make('community_name')->label('社群'),
            TextEntry::make('status')
                ->label('狀態')
                ->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)
                ->badge(),
            TextEntry::make('created_at')->label('送出時間')->dateTime(),
            TextEntry::make('subject')->label('主旨')->columnSpanFull(),
            TextEntry::make('body')->label('內容')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
            TextEntry::make('handler.name')->label('處理人')->placeholder('尚未處理'),
            TextEntry::make('handled_at')->label('處理時間')->dateTime()->placeholder('尚未處理'),
            TextEntry::make('admin_note')->label('備註')->placeholder('沒有備註')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
        ]);
    }
}

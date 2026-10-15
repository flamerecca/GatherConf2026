<?php

namespace App\Filament\Resources\DriftBottles\Schemas;

use App\Models\DriftBottle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DriftBottleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('sender.name')->label('寄件者'),
            TextEntry::make('sender.email')->label('寄件者 Email'),
            TextEntry::make('recipient.name')->label('收件者')->placeholder('漂流中'),
            TextEntry::make('recipient.email')->label('收件者 Email')->placeholder('漂流中'),
            TextEntry::make('status')
                ->label('狀態')
                ->state(fn (DriftBottle $record): string => DriftBottlesLabels::status($record->status()))
                ->badge(),
            TextEntry::make('created_at')->label('丟出時間')->dateTime(),
            TextEntry::make('assigned_at')->label('指派時間')->dateTime()->placeholder('尚未指派'),
            TextEntry::make('opened_at')->label('開啟時間')->dateTime()->placeholder('尚未開啟'),
            TextEntry::make('body')->label('內容')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            TextEntry::make('reply_body')->label('回覆')->placeholder('沒有回覆')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            TextEntry::make('replied_at')->label('回覆時間')->dateTime()->placeholder('沒有回覆'),
            TextEntry::make('removed_at')->label('瓶子下架時間')->dateTime()->placeholder('未下架'),
            TextEntry::make('reply_removed_at')->label('回覆下架時間')->dateTime()->placeholder('未下架'),
        ]);
    }
}

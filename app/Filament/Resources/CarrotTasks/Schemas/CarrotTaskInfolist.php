<?php

namespace App\Filament\Resources\CarrotTasks\Schemas;

use App\Models\CarrotTask;
use App\View\Components\QrCode;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CarrotTaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('任務名稱'),
            TextEntry::make('description')->label('說明')->placeholder('未填寫'),
            TextEntry::make('reward')->label('獎勵胡蘿蔔數量'),
            IconEntry::make('is_active')->label('啟用')->boolean(),
            TextEntry::make('starts_at')->label('開始時間')->dateTime()->placeholder('不限'),
            TextEntry::make('ends_at')->label('結束時間')->dateTime()->placeholder('不限'),
            TextEntry::make('scan_url')
                ->label('掃描網址')
                ->state(fn (CarrotTask $record): string => $record->completeUrl('zh_TW'))
                ->copyable()
                ->columnSpanFull(),
            TextEntry::make('qr')
                ->label('QR Code')
                ->state(fn (CarrotTask $record): HtmlString => new HtmlString(
                    '<img src="'.(new QrCode($record->completeUrl('zh_TW')))->dataUri().'" alt="QR Code" class="size-64 rounded-xl bg-white p-3">'
                ))
                ->html()
                ->columnSpanFull(),
        ]);
    }
}

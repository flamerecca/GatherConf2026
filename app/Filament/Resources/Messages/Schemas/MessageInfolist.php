<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\Message;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('sender.name')->label('寄件者'),
            TextEntry::make('sender.email')->label('寄件者 Email'),
            TextEntry::make('recipient.name')->label('收件者'),
            TextEntry::make('recipient.email')->label('收件者 Email'),
            TextEntry::make('created_at')->label('傳送時間')->dateTime(),
            TextEntry::make('read_at')->label('已讀時間')->dateTime()->placeholder('未讀'),
            TextEntry::make('body')->label('內容')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            RepeatableEntry::make('context')
                ->label('同一配對的前後訊息')
                ->columnSpanFull()
                ->state(fn (Message $record): array => self::context($record))
                ->schema([
                    TextEntry::make('sender')->label('寄件者'),
                    TextEntry::make('created_at')->label('時間'),
                    TextEntry::make('body')->label('內容')->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
        ]);
    }

    /**
     * @return array<int, array{sender: string, created_at: string, body: string}>
     */
    private static function context(Message $record): array
    {
        $before = Message::query()
            ->between($record->sender, $record->recipient)
            ->where('id', '<', $record->getKey())
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->reverse();

        $after = Message::query()
            ->between($record->sender, $record->recipient)
            ->where('id', '>', $record->getKey())
            ->orderBy('id')
            ->limit(5)
            ->get();

        return $before->concat($after)
            ->map(fn (Message $message): array => [
                'sender' => $message->sender->name,
                'created_at' => $message->created_at->format('Y-m-d H:i'),
                'body' => $message->body,
            ])
            ->values()
            ->all();
    }
}

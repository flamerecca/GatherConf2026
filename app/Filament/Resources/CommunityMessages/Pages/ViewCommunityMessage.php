<?php

namespace App\Filament\Resources\CommunityMessages\Pages;

use App\Filament\Resources\CommunityMessages\CommunityMessageResource;
use App\Models\CommunityMessage;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCommunityMessage extends ViewRecord
{
    protected static string $resource = CommunityMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markForwarded')
                ->label('標記為已轉交')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (CommunityMessage $record): bool => $record->status !== CommunityMessage::STATUS_FORWARDED)
                ->action(function (CommunityMessage $record): void {
                    $record->markAs(CommunityMessage::STATUS_FORWARDED, auth()->id());

                    Notification::make()->title('已標記為已轉交')->success()->send();
                }),
            Action::make('markClosed')
                ->label('標記為已結案')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (CommunityMessage $record): bool => $record->status !== CommunityMessage::STATUS_CLOSED)
                ->action(function (CommunityMessage $record): void {
                    $record->markAs(CommunityMessage::STATUS_CLOSED, auth()->id());

                    Notification::make()->title('已標記為已結案')->success()->send();
                }),
            Action::make('reopen')
                ->label('重新開啟')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (CommunityMessage $record): bool => $record->status !== CommunityMessage::STATUS_PENDING)
                ->action(function (CommunityMessage $record): void {
                    $record->markAs(CommunityMessage::STATUS_PENDING, auth()->id());

                    Notification::make()->title('已重新開啟')->success()->send();
                }),
            Action::make('editNote')
                ->label('編輯備註')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->fillForm(fn (CommunityMessage $record): array => ['admin_note' => $record->admin_note])
                ->schema([
                    Textarea::make('admin_note')->label('備註，只有管理員看得到')->maxLength(2000),
                ])
                ->action(function (array $data, CommunityMessage $record): void {
                    $record->forceFill(['admin_note' => $data['admin_note'] ?? null])->save();

                    Notification::make()->title('已更新備註')->success()->send();
                }),
        ];
    }
}

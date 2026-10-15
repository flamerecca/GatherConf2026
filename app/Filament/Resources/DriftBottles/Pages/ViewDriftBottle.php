<?php

namespace App\Filament\Resources\DriftBottles\Pages;

use App\Filament\Resources\DriftBottles\DriftBottleResource;
use App\Models\DriftBottle;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDriftBottle extends ViewRecord
{
    protected static string $resource = DriftBottleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('removeBottle')
                ->label('下架瓶子')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (DriftBottle $record): bool => $record->removed_at === null)
                ->action(function (DriftBottle $record): void {
                    $record->removeBottle();

                    Notification::make()->title('已下架瓶子')->success()->send();
                }),
            Action::make('restoreBottle')
                ->label('還原瓶子')
                ->icon(Heroicon::OutlinedEye)
                ->requiresConfirmation()
                ->visible(fn (DriftBottle $record): bool => $record->removed_at !== null)
                ->action(function (DriftBottle $record): void {
                    $record->restoreBottle();

                    Notification::make()->title('已還原瓶子')->success()->send();
                }),
            Action::make('removeReply')
                ->label('下架回覆')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (DriftBottle $record): bool => $record->replied_at !== null && $record->reply_removed_at === null)
                ->action(function (DriftBottle $record): void {
                    $record->removeReply();

                    Notification::make()->title('已下架回覆')->success()->send();
                }),
            Action::make('restoreReply')
                ->label('還原回覆')
                ->icon(Heroicon::OutlinedEye)
                ->requiresConfirmation()
                ->visible(fn (DriftBottle $record): bool => $record->reply_removed_at !== null)
                ->action(function (DriftBottle $record): void {
                    $record->restoreReply();

                    Notification::make()->title('已還原回覆')->success()->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\Members\Actions;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class MemberActions
{
    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('停用')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('停用會員')
            ->modalDescription('停用後，此會員將無法登入官網。')
            ->visible(fn (Member $record): bool => $record->is_active)
            ->action(function (Member $record): void {
                $record->update(['is_active' => false]);

                Notification::make()->title('已停用會員')->success()->send();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('啟用')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Member $record): bool => ! $record->is_active)
            ->action(function (Member $record): void {
                $record->update(['is_active' => true]);

                Notification::make()->title('已啟用會員')->success()->send();
            });
    }

    public static function markVerified(): Action
    {
        return Action::make('markVerified')
            ->label('標記為已驗證')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->requiresConfirmation()
            ->visible(fn (Member $record): bool => $record->email_verified_at === null)
            ->action(function (Member $record): void {
                $record->forceFill(['email_verified_at' => now()])->save();

                Notification::make()->title('已標記為已驗證')->success()->send();
            });
    }
}

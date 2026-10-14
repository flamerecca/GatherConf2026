<?php

namespace App\Filament\Resources\Members\Actions;

use App\Models\CarrotLedger;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

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

    public static function adjustCarrots(): Action
    {
        return Action::make('adjustCarrots')
            ->label('調整胡蘿蔔')
            ->icon(Heroicon::OutlinedGift)
            ->modalHeading('調整胡蘿蔔')
            ->schema([
                TextInput::make('amount')
                    ->label('數量，正數增加、負數扣除')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->rules(['not_in:0']),
                TextInput::make('note')
                    ->label('原因')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, Member $record): void {
                try {
                    CarrotLedger::adjust($record, (int) $data['amount'], $data['note'], auth()->id());
                } catch (DomainException) {
                    Notification::make()->title('扣除後胡蘿蔔會小於 0，未調整')->danger()->send();

                    return;
                }

                Notification::make()->title('已調整胡蘿蔔')->success()->send();
            });
    }

    public static function sendLoginLink(): Action
    {
        return Action::make('sendLoginLink')
            ->label('寄送登入連結')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalHeading('寄送登入連結')
            ->modalDescription('將一封一次性登入連結寄到會員自己的 Email，並作廢先前尚未使用的連結。你不會看到連結內容。')
            ->visible(fn (Member $record): bool => $record->is_active)
            ->action(function (Member $record): void {
                $key = MemberLoginLink::throttleKey($record->email);

                if (RateLimiter::tooManyAttempts($key, MemberLoginLink::HOURLY_LIMIT)) {
                    Notification::make()
                        ->title('寄送過於頻繁')
                        ->body('此會員一小時內已要求過多次登入連結，請稍後再試。')
                        ->warning()
                        ->send();

                    return;
                }

                RateLimiter::hit($key, 3600);

                $token = MemberLoginLink::issueFor($record);

                try {
                    $record->notify(
                        (new MemberLoginLinkNotification(route('member.login.link.consume', ['token' => $token])))->locale('zh_TW'),
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('寄送失敗')
                        ->body('信件無法寄出，請檢查郵件服務設定後再試。')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()->title('已寄送登入連結')->success()->send();
            });
    }
}

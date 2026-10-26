<?php

namespace App\Filament\Resources\Members\Pages;

use App\Actions\PurgeGuestQuizAttempts;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Widgets\BbtiDistributionChart;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purgeGuestQuizAttempts')
                ->label('清除未綁定會員的測驗報告')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('清除未綁定會員的測驗報告')
                ->modalDescription('會永久刪除所有沒有綁定會員的 BBTI 報告與專屬兔子圖片，無法還原。已綁定會員的報告不受影響。')
                ->action(function (PurgeGuestQuizAttempts $purge): void {
                    Notification::make()->title('已清除 '.$purge().' 份報告')->success()->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BbtiDistributionChart::class,
        ];
    }
}

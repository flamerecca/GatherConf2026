<?php

namespace App\Filament\Resources\QuizAttempts\Pages;

use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use App\Models\QuizAttempt;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListQuizAttempts extends ListRecords
{
    protected static string $resource = QuizAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearGuestAttempts')
                ->label('清除訪客測驗')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->modalHeading('清除訪客測驗')
                ->modalDescription('會清除沒有綁定會員的測驗結果，包含專屬兔子圖。已綁定會員的結果不受影響，被清除的結果連結會變成 404。')
                ->modalSubmitActionLabel('清除')
                ->schema([
                    DatePicker::make('before')
                        ->label('清除這個日期以前完成的結果')
                        ->helperText('當天完成的結果不會被清除。')
                        ->default(fn (): string => now()->subDays(30)->toDateString())
                        ->maxDate(fn (): string => now()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $cleared = QuizAttempt::clearGuests(Carbon::parse($data['before'])->startOfDay());

                    Notification::make()->title("已清除 {$cleared} 筆訪客測驗")->success()->send();
                }),
        ];
    }
}

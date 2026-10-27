<?php

namespace App\Filament\Resources\DriftBottles\Pages;

use App\Filament\Resources\DriftBottles\DriftBottleResource;
use App\Models\DriftBottle;
use App\Models\NpcCharacter;
use App\Notifications\DriftBottleReceivedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListDriftBottles extends ListRecords
{
    protected static string $resource = DriftBottleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('throwSystemBottle')
                ->label('系統漂流瓶')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading('從系統發送漂流瓶')
                ->modalDescription('漂流瓶會直接送給一位隨機會員，會員開啟後可以回覆，回覆內容會顯示在這裡。')
                ->modalSubmitActionLabel('發送')
                ->schema([
                    Select::make('npc_character_id')
                        ->label('發送角色')
                        ->options(fn (): array => NpcCharacter::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('系統')
                        ->helperText('選擇 NPC 角色時，瓶子會以該角色綁定的會員作為寄件者。'),
                    Textarea::make('body')
                        ->label('內容')
                        ->required()
                        ->maxLength(DriftBottle::MAX_LENGTH)
                        ->rows(5),
                ])
                ->action(function (array $data): void {
                    $bottle = DriftBottle::throwFromSystem(
                        trim($data['body']),
                        filled($data['npc_character_id'] ?? null) ? NpcCharacter::query()->find($data['npc_character_id']) : null,
                    );

                    if ($bottle === null) {
                        Notification::make()->title('目前沒有可以收到漂流瓶的會員')->warning()->send();

                        return;
                    }

                    try {
                        $bottle->recipient->notify(
                            (new DriftBottleReceivedNotification(route('member.bottles.show', $bottle)))->locale('zh_TW'),
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }

                    Notification::make()->title("已發送給 {$bottle->recipient->name}")->success()->send();
                }),
        ];
    }
}

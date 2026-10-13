<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\MemberLoginLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'loginLinks';

    protected static ?string $title = '登入連結';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('到期時間')
                    ->dateTime(),
                TextColumn::make('used_at')
                    ->label('使用時間')
                    ->dateTime()
                    ->placeholder('尚未使用'),
                TextColumn::make('status')
                    ->label('狀態')
                    ->state(fn (MemberLoginLink $record): string => match ($record->status()) {
                        MemberLoginLink::STATUS_USED => '已使用',
                        MemberLoginLink::STATUS_EXPIRED => '已過期',
                        MemberLoginLink::STATUS_REVOKED => '已作廢',
                        default => '可使用',
                    })
                    ->badge()
                    ->color(fn (MemberLoginLink $record): string => match ($record->status()) {
                        MemberLoginLink::STATUS_USABLE => 'success',
                        MemberLoginLink::STATUS_USED => 'gray',
                        MemberLoginLink::STATUS_EXPIRED => 'warning',
                        MemberLoginLink::STATUS_REVOKED => 'danger',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('revoke')
                    ->label('作廢')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('作廢登入連結')
                    ->modalDescription('作廢後，此連結將無法再用於登入。')
                    ->visible(fn (MemberLoginLink $record): bool => $record->isUsable())
                    ->action(function (MemberLoginLink $record): void {
                        if ($record->revoke()) {
                            Notification::make()->title('已作廢登入連結')->success()->send();
                        }
                    }),
            ]);
    }
}

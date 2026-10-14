<?php

namespace App\Filament\Resources\Friendships\Tables;

use App\Models\Friendship;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FriendshipsTable
{
    public static function configure(Table $table): Table
    {
        $memberSearch = function (string $relation): Closure {
            return fn (Builder $query, string $search): Builder => $query->whereHas(
                $relation,
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            );
        };

        return $table
            ->columns([
                TextColumn::make('low.name')
                    ->label('會員甲')
                    ->searchable(query: $memberSearch('low')),
                TextColumn::make('high.name')
                    ->label('會員乙')
                    ->searchable(query: $memberSearch('high')),
                TextColumn::make('requester.name')
                    ->label('邀請者'),
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Friendship::STATUS_ACCEPTED ? '已成為好友' : '等待回覆')
                    ->color(fn (string $state): string => $state === Friendship::STATUS_ACCEPTED ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('responded_at')
                    ->label('回應時間')
                    ->dateTime()
                    ->placeholder('尚未回應'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        Friendship::STATUS_PENDING => '等待回覆',
                        Friendship::STATUS_ACCEPTED => '已成為好友',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

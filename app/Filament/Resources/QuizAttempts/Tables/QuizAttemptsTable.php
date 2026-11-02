<?php

namespace App\Filament\Resources\QuizAttempts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuizAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'personalityType']))
            ->columns([
                TextColumn::make('type_code')
                    ->label('類型')
                    ->badge()
                    ->description(fn ($record): ?string => $record->personalityType?->name)
                    ->searchable(),
                TextColumn::make('member.name')
                    ->label('會員')
                    ->placeholder('訪客')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'member',
                        fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"),
                    )),
                IconColumn::make('unique_bunny_path')
                    ->label('專屬兔子圖')
                    ->boolean()
                    ->state(fn ($record): bool => $record->unique_bunny_path !== null),
                TextColumn::make('completed_at')->label('完成時間')->dateTime()->sortable(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->filters([
                TernaryFilter::make('bound')
                    ->label('綁定會員')
                    ->placeholder('全部')
                    ->trueLabel('已綁定會員')
                    ->falseLabel('訪客')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                DeleteAction::make()->modalDescription('刪除後這份結果的連結會變成 404，專屬兔子圖也會一併移除。'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

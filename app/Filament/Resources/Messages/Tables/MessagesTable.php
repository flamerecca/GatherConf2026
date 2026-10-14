<?php

namespace App\Filament\Resources\Messages\Tables;

use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesTable
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
                TextColumn::make('sender.name')
                    ->label('寄件者')
                    ->searchable(query: $memberSearch('sender')),
                TextColumn::make('recipient.name')
                    ->label('收件者')
                    ->searchable(query: $memberSearch('recipient')),
                TextColumn::make('body')
                    ->label('內容')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('傳送時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label('已讀時間')
                    ->dateTime()
                    ->placeholder('未讀'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('read_at')
                    ->label('已讀狀態')
                    ->nullable()
                    ->trueLabel('已讀')
                    ->falseLabel('未讀'),
                Filter::make('created_at')
                    ->label('傳送日期')
                    ->schema([
                        DatePicker::make('from')->label('從'),
                        DatePicker::make('until')->label('到'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

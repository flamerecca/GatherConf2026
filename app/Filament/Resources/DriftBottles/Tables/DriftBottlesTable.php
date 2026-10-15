<?php

namespace App\Filament\Resources\DriftBottles\Tables;

use App\Filament\Resources\DriftBottles\Schemas\DriftBottlesLabels;
use App\Models\DriftBottle;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriftBottlesTable
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
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'reports as pending_reports_count' => fn (Builder $query) => $query->whereNull('reviewed_at'),
            ]))
            ->columns([
                TextColumn::make('sender.name')
                    ->label('寄件者')
                    ->searchable(query: $memberSearch('sender')),
                TextColumn::make('recipient.name')
                    ->label('收件者')
                    ->placeholder('漂流中')
                    ->searchable(query: $memberSearch('recipient')),
                TextColumn::make('body')
                    ->label('內容')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('狀態')
                    ->state(fn (DriftBottle $record): string => DriftBottlesLabels::status($record->status()))
                    ->badge(),
                TextColumn::make('pending_reports_count')
                    ->label('待處理檢舉')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->label('丟出時間')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options(DriftBottlesLabels::statuses())
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            DriftBottle::STATUS_REMOVED => $query->whereNotNull('removed_at'),
                            DriftBottle::STATUS_REPLIED => $query->whereNull('removed_at')->whereNotNull('replied_at'),
                            DriftBottle::STATUS_OPENED => $query->whereNull('removed_at')->whereNull('replied_at')->whereNotNull('opened_at'),
                            DriftBottle::STATUS_DELIVERED => $query->whereNull('removed_at')->whereNull('opened_at')->whereNotNull('recipient_id'),
                            DriftBottle::STATUS_IN_SEA => $query->whereNull('removed_at')->whereNull('opened_at')->whereNull('recipient_id'),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('pending_reports')
                    ->label('待處理檢舉')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('reports', fn (Builder $q) => $q->whereNull('reviewed_at')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('reports', fn (Builder $q) => $q->whereNull('reviewed_at')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->label('丟出日期')
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

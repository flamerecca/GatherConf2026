<?php

namespace App\Filament\Resources\CarrotTransactions\Tables;

use App\Models\CarrotLedger;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CarrotTransactionsTable
{
    public static function configure(Table $table): Table
    {
        $reasons = collect(array_keys(CarrotLedger::siteRewards()))
            ->push(CarrotLedger::REASON_TASK, CarrotLedger::REASON_TASK_REVOKED, CarrotLedger::REASON_MANUAL)
            ->mapWithKeys(fn (string $reason) => [$reason => $reason])
            ->all();

        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('會員')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'member',
                        fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"),
                    )),
                TextColumn::make('amount')
                    ->label('數量')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state)
                    ->sortable(),
                TextColumn::make('reason')->label('原因')->badge(),
                TextColumn::make('note')->label('說明')->placeholder('無'),
                TextColumn::make('task.name')->label('來源任務')->placeholder('無'),
                TextColumn::make('created_at')->label('時間')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reason')->label('原因')->options($reasons),
            ]);
    }
}

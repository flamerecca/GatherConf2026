<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CarrotTaskCompletionsRelationManager extends RelationManager
{
    protected static string $relationship = 'carrotTransactions';

    protected static ?string $title = '任務完成狀態';

    public function table(Table $table): Table
    {
        /** @var Member $member */
        $member = $this->getOwnerRecord();
        $completedBy = fn (Builder $query) => $query->where('member_id', $member->getKey());

        return $table
            ->query(
                CarrotTask::query()->withExists(['transactions as is_completed' => $completedBy]),
            )
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('任務名稱')->searchable(),
                TextColumn::make('reward')->label('獎勵'),
                IconColumn::make('is_completed')->label('已完成')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_completed')
                    ->label('完成狀態')
                    ->placeholder('全部')
                    ->trueLabel('已完成')
                    ->falseLabel('未完成')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('transactions', $completedBy),
                        false: fn (Builder $query) => $query->whereDoesntHave('transactions', $completedBy),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('markCompleted')
                    ->label('標記為已完成')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('將依任務獎勵為此會員加上胡蘿蔔，且只會發放一次。')
                    ->visible(fn (CarrotTask $record): bool => ! $record->is_completed)
                    ->action(function (CarrotTask $record) use ($member): void {
                        $earned = CarrotLedger::award(
                            $member,
                            CarrotLedger::REASON_TASK,
                            $record->reward,
                            $record->dedupeKeyFor($member),
                            $record,
                        );

                        Notification::make()
                            ->title($earned ? '已標記為完成' : '此任務先前已完成')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at');
    }
}

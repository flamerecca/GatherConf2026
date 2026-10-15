<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\CarrotLedger;
use App\Models\CarrotTransaction;
use App\Models\Member;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CarrotSiteTaskCompletionsRelationManager extends RelationManager
{
    protected static string $relationship = 'carrotTransactions';

    protected static ?string $title = '站上任務完成狀態';

    public function table(Table $table): Table
    {
        /** @var Member $member */
        $member = $this->getOwnerRecord();

        return $table
            ->records(function () use ($member): array {
                $keys = collect(CarrotLedger::siteRewards())
                    ->keys()
                    ->flatMap(fn (string $code): array => [
                        CarrotLedger::siteDedupeKey($code, $member),
                        'revoke:'.CarrotLedger::siteDedupeKey($code, $member),
                    ]);

                $present = CarrotTransaction::query()
                    ->where('member_id', $member->getKey())
                    ->whereIn('dedupe_key', $keys)
                    ->pluck('dedupe_key')
                    ->flip();

                return collect(CarrotLedger::siteRewards())
                    ->map(function (int $reward, string $code) use ($member, $present): array {
                        $key = CarrotLedger::siteDedupeKey($code, $member);

                        return [
                            'code' => $code,
                            'name' => __('carrots.site.'.$code.'.name', [], 'zh_TW'),
                            'reward' => $reward,
                            'has_completion' => $present->has($key),
                            'is_revoked' => $present->has('revoke:'.$key),
                        ];
                    })
                    ->all();
            })
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('name')->label('任務名稱'),
                TextColumn::make('reward')->label('獎勵'),
                IconColumn::make('is_completed')
                    ->label('已完成')
                    ->state(fn (array $record): bool => $record['has_completion'] && ! $record['is_revoked'])
                    ->boolean(),
                TextColumn::make('remark')
                    ->label('備註')
                    ->state(fn (array $record): ?string => $record['is_revoked'] ? '已取消完成' : null)
                    ->placeholder('無'),
            ])
            ->recordActions([
                Action::make('markCompleted')
                    ->label('標記為已完成')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('將依獎勵為此會員加上胡蘿蔔，且只會發放一次。')
                    ->visible(fn (array $record): bool => ! $record['has_completion'] && $record['reward'] > 0)
                    ->action(function (array $record) use ($member): void {
                        try {
                            $earned = CarrotLedger::award(
                                $member,
                                $record['code'],
                                $record['reward'],
                                CarrotLedger::siteDedupeKey($record['code'], $member),
                            );
                        } catch (DomainException) {
                            Notification::make()->title('發放後胡蘿蔔超出可儲存的範圍，未標記')->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title($earned ? '已標記為完成' : '此任務先前已完成')
                            ->success()
                            ->send();
                    }),
                Action::make('revokeCompletion')
                    ->label('取消完成')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('取消任務完成')
                    ->modalDescription('將扣回此任務發放的胡蘿蔔，並留下一筆扣回紀錄。取消後，會員無法再重新完成同一個任務。')
                    ->visible(fn (array $record): bool => $record['has_completion'] && ! $record['is_revoked'])
                    ->action(function (array $record) use ($member): void {
                        try {
                            $revoked = CarrotLedger::revokeSiteReward($member, $record['code'], auth()->id());
                        } catch (DomainException) {
                            Notification::make()->title('扣回後胡蘿蔔超出可儲存的範圍，未取消')->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title($revoked !== null ? '已取消完成' : '此任務尚未完成或已取消')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

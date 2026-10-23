<?php

namespace App\Filament\Resources\Members\Tables;

use App\Filament\Resources\Members\Actions\MemberActions;
use App\Models\Member;
use App\Models\PersonalityType;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('暱稱')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization')
                    ->label('所屬社群或公司')
                    ->searchable()
                    ->placeholder('未填寫'),
                TextColumn::make('latestQuizAttempt.type_code')
                    ->label('BBTI')
                    ->description(fn (Member $record): ?string => $record->latestQuizAttempt?->personalityType?->name)
                    ->badge()
                    ->placeholder('尚未測驗'),
                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),
                IconColumn::make('has_password')
                    ->label('已設定密碼')
                    ->state(fn (Member $record): bool => $record->hasPassword())
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Email 驗證')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('註冊時間')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('bbti')
                    ->label('BBTI')
                    ->options(fn (): array => PersonalityType::query()->orderBy('code')->get()->mapWithKeys(fn (PersonalityType $type): array => [$type->code => $type->code.' '.$type->name])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereHas('latestQuizAttempt', fn (Builder $attempt) => $attempt->where('type_code', $data['value']))
                        : $query),
                TernaryFilter::make('is_active')
                    ->label('啟用狀態')
                    ->trueLabel('啟用')
                    ->falseLabel('停用'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email 驗證')
                    ->nullable()
                    ->trueLabel('已驗證')
                    ->falseLabel('未驗證'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                MemberActions::deactivate(),
                MemberActions::activate(),
                MemberActions::markVerified(),
                MemberActions::sendLoginLink(),
            ]);
    }
}

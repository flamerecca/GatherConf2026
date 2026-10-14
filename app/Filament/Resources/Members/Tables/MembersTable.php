<?php

namespace App\Filament\Resources\Members\Tables;

use App\Filament\Resources\Members\Actions\MemberActions;
use App\Models\Member;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('姓名')
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

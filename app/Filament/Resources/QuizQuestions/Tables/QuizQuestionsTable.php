<?php

namespace App\Filament\Resources\QuizQuestions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuizQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order')->label('順序')->sortable(),
                TextColumn::make('axis')->label('維度')->badge(),
                TextColumn::make('positive_pole')->label('傾向極性')->badge(),
                TextColumn::make('text')->label('題目')->searchable()->wrap(),
                IconColumn::make('is_active')->label('啟用')->boolean(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                SelectFilter::make('axis')->label('維度')->options([
                    'NM' => 'NM 探索心態',
                    'SV' => 'SV 技術偏好',
                    'HA' => 'HA 工具哲學',
                    'GD' => 'GD 聚落動態',
                ]),
                TernaryFilter::make('is_active')->label('啟用'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

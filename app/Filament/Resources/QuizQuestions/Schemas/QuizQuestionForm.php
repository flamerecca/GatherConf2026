<?php

namespace App\Filament\Resources\QuizQuestions\Schemas;

use App\Models\QuizQuestion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QuizQuestionForm
{
    /**
     * Each axis with the two poles a question on it can lean toward.
     *
     * @var array<string, array<string, string>>
     */
    public const POLES = [
        'NM' => ['N' => 'N 新奇開拓', 'M' => 'M 穩固成熟'],
        'SV' => ['S' => 'S 系統', 'V' => 'V 視覺'],
        'HA' => ['H' => 'H 親手介入', 'A' => 'A 自動化生成'],
        'GD' => ['G' => 'G 熱情採集', 'D' => 'D 深入探索'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('axis')
                ->label('所屬維度')
                ->options([
                    'NM' => 'NM 探索心態',
                    'SV' => 'SV 技術偏好',
                    'HA' => 'HA 工具哲學',
                    'GD' => 'GD 聚落動態',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('positive_pole', null)),
            Select::make('positive_pole')
                ->label('傾向極性')
                ->helperText('使用者選「同意」時，代表偏向哪一極。')
                ->options(fn (Get $get): array => self::POLES[$get('axis')] ?? [])
                ->required()
                ->in(fn (Get $get): array => array_keys(self::POLES[$get('axis')] ?? [])),
            Textarea::make('text')
                ->label('題目文字')
                ->required()
                ->maxLength(255)
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('order')
                ->label('顯示順序')
                ->helperText('數字越小越早出現。')
                ->required()
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(fn (): int => (int) QuizQuestion::query()->max('order') + 1),
            Toggle::make('is_active')
                ->label('啟用')
                ->helperText('要拿掉一題請關閉，不要刪除。')
                ->default(true),
        ]);
    }
}

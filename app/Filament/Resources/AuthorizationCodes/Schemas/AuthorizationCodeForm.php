<?php

namespace App\Filament\Resources\AuthorizationCodes\Schemas;

use App\Models\AuthorizationCode;
use App\Models\CarrotTask;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AuthorizationCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名稱')
                ->helperText('給管理員辨識用，會員看不到。')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label('授權碼')
                ->helperText('留空會自動產生 8 碼。會員輸入時不分大小寫，也可以加空格或連字號。')
                ->maxLength(32)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? AuthorizationCode::normalize($state) : null)
                ->unique(ignoreRecord: true)
                ->rules([
                    fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (filled($value) && CarrotTask::query()->where('short_code', AuthorizationCode::normalize((string) $value))->exists()) {
                            $fail('這組代碼已經是現場任務的短碼。');
                        }
                    },
                ]),
            TextInput::make('reward')
                ->label('獎勵胡蘿蔔數量')
                ->required()
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100000),
            TextInput::make('max_uses')
                ->label('可兌換次數上限')
                ->helperText('全部會員合計最多可以兌換幾次，留空代表不限。每位會員同一組授權碼只能兌換一次。')
                ->numeric()
                ->integer()
                ->minValue(1),
            Toggle::make('is_active')
                ->label('啟用')
                ->default(true),
            DateTimePicker::make('starts_at')
                ->label('開始時間'),
            DateTimePicker::make('ends_at')
                ->label('結束時間'),
        ]);
    }
}

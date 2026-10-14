<?php

namespace App\Filament\Resources\CarrotTransactions;

use App\Filament\Resources\CarrotTransactions\Pages\ListCarrotTransactions;
use App\Filament\Resources\CarrotTransactions\Tables\CarrotTransactionsTable;
use App\Models\CarrotTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CarrotTransactionResource extends Resource
{
    protected static ?string $model = CarrotTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = '胡蘿蔔明細';

    protected static ?string $pluralModelLabel = '胡蘿蔔明細';

    public static function table(Table $table): Table
    {
        return CarrotTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCarrotTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}

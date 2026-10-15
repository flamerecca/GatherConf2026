<?php

namespace App\Filament\Resources\DriftBottles;

use App\Filament\Resources\DriftBottles\Pages\ListDriftBottles;
use App\Filament\Resources\DriftBottles\Pages\ViewDriftBottle;
use App\Filament\Resources\DriftBottles\RelationManagers\ReportsRelationManager;
use App\Filament\Resources\DriftBottles\Schemas\DriftBottleInfolist;
use App\Filament\Resources\DriftBottles\Tables\DriftBottlesTable;
use App\Models\DriftBottle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DriftBottleResource extends Resource
{
    protected static ?string $model = DriftBottle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = '漂流瓶';

    protected static ?string $pluralModelLabel = '漂流瓶';

    public static function infolist(Schema $schema): Schema
    {
        return DriftBottleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriftBottlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriftBottles::route('/'),
            'view' => ViewDriftBottle::route('/{record}'),
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

<?php

namespace App\Filament\Resources\PersonalityTypes;

use App\Filament\Resources\PersonalityTypes\Pages\EditPersonalityType;
use App\Filament\Resources\PersonalityTypes\Pages\ListPersonalityTypes;
use App\Filament\Resources\PersonalityTypes\Schemas\PersonalityTypeForm;
use App\Filament\Resources\PersonalityTypes\Tables\PersonalityTypesTable;
use App\Models\PersonalityType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PersonalityTypeResource extends Resource
{
    protected static ?string $model = PersonalityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'BBTI 測驗';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '性格類型';

    protected static ?string $pluralModelLabel = '性格類型';

    public static function form(Schema $schema): Schema
    {
        return PersonalityTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonalityTypesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonalityTypes::route('/'),
            'edit' => EditPersonalityType::route('/{record}/edit'),
        ];
    }
}

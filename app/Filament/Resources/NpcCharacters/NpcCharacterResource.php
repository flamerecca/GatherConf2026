<?php

namespace App\Filament\Resources\NpcCharacters;

use App\Filament\Resources\NpcCharacters\Pages\CreateNpcCharacter;
use App\Filament\Resources\NpcCharacters\Pages\EditNpcCharacter;
use App\Filament\Resources\NpcCharacters\Pages\ListNpcCharacters;
use App\Filament\Resources\NpcCharacters\Schemas\NpcCharacterForm;
use App\Filament\Resources\NpcCharacters\Tables\NpcCharactersTable;
use App\Models\NpcCharacter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NpcCharacterResource extends Resource
{
    protected static ?string $model = NpcCharacter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'NPC 角色';

    protected static ?string $pluralModelLabel = 'NPC 角色';

    public static function form(Schema $schema): Schema
    {
        return NpcCharacterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NpcCharactersTable::configure($table);
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
            'index' => ListNpcCharacters::route('/'),
            'create' => CreateNpcCharacter::route('/create'),
            'edit' => EditNpcCharacter::route('/{record}/edit'),
        ];
    }
}

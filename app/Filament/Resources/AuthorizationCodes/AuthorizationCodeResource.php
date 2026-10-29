<?php

namespace App\Filament\Resources\AuthorizationCodes;

use App\Filament\Resources\AuthorizationCodes\Pages\CreateAuthorizationCode;
use App\Filament\Resources\AuthorizationCodes\Pages\EditAuthorizationCode;
use App\Filament\Resources\AuthorizationCodes\Pages\ListAuthorizationCodes;
use App\Filament\Resources\AuthorizationCodes\Schemas\AuthorizationCodeForm;
use App\Filament\Resources\AuthorizationCodes\Tables\AuthorizationCodesTable;
use App\Models\AuthorizationCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuthorizationCodeResource extends Resource
{
    protected static ?string $model = AuthorizationCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = '授權碼';

    protected static ?string $pluralModelLabel = '授權碼';

    public static function form(Schema $schema): Schema
    {
        return AuthorizationCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorizationCodesTable::configure($table);
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
            'index' => ListAuthorizationCodes::route('/'),
            'create' => CreateAuthorizationCode::route('/create'),
            'edit' => EditAuthorizationCode::route('/{record}/edit'),
        ];
    }
}

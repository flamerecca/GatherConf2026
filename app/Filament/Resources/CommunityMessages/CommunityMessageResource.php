<?php

namespace App\Filament\Resources\CommunityMessages;

use App\Filament\Resources\CommunityMessages\Pages\ListCommunityMessages;
use App\Filament\Resources\CommunityMessages\Pages\ViewCommunityMessage;
use App\Filament\Resources\CommunityMessages\Schemas\CommunityMessageInfolist;
use App\Filament\Resources\CommunityMessages\Tables\CommunityMessagesTable;
use App\Models\CommunityMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CommunityMessageResource extends Resource
{
    protected static ?string $model = CommunityMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = '社群訊息';

    protected static ?string $pluralModelLabel = '社群訊息';

    public static function infolist(Schema $schema): Schema
    {
        return CommunityMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityMessagesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = CommunityMessage::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityMessages::route('/'),
            'view' => ViewCommunityMessage::route('/{record}'),
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

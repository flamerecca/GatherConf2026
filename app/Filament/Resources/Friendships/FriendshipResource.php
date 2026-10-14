<?php

namespace App\Filament\Resources\Friendships;

use App\Filament\Resources\Friendships\Pages\ListFriendships;
use App\Filament\Resources\Friendships\Pages\ViewFriendship;
use App\Filament\Resources\Friendships\Schemas\FriendshipInfolist;
use App\Filament\Resources\Friendships\Tables\FriendshipsTable;
use App\Models\Friendship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FriendshipResource extends Resource
{
    protected static ?string $model = Friendship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '好友關係';

    protected static ?string $pluralModelLabel = '好友關係';

    public static function infolist(Schema $schema): Schema
    {
        return FriendshipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FriendshipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFriendships::route('/'),
            'view' => ViewFriendship::route('/{record}'),
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

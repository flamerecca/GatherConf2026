<?php

namespace App\Filament\Resources\CarrotTasks;

use App\Filament\Resources\CarrotTasks\Pages\CreateCarrotTask;
use App\Filament\Resources\CarrotTasks\Pages\EditCarrotTask;
use App\Filament\Resources\CarrotTasks\Pages\ListCarrotTasks;
use App\Filament\Resources\CarrotTasks\Pages\ViewCarrotTask;
use App\Filament\Resources\CarrotTasks\Schemas\CarrotTaskForm;
use App\Filament\Resources\CarrotTasks\Schemas\CarrotTaskInfolist;
use App\Filament\Resources\CarrotTasks\Tables\CarrotTasksTable;
use App\Models\CarrotTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CarrotTaskResource extends Resource
{
    protected static ?string $model = CarrotTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '胡蘿蔔任務';

    protected static ?string $pluralModelLabel = '胡蘿蔔任務';

    public static function form(Schema $schema): Schema
    {
        return CarrotTaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CarrotTaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CarrotTasksTable::configure($table);
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
            'index' => ListCarrotTasks::route('/'),
            'create' => CreateCarrotTask::route('/create'),
            'view' => ViewCarrotTask::route('/{record}'),
            'edit' => EditCarrotTask::route('/{record}/edit'),
        ];
    }
}

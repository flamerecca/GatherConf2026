<?php

namespace App\Filament\Resources\CarrotTasks\Pages;

use App\Filament\Resources\CarrotTasks\CarrotTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCarrotTasks extends ListRecords
{
    protected static string $resource = CarrotTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

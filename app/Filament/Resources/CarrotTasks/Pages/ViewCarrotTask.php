<?php

namespace App\Filament\Resources\CarrotTasks\Pages;

use App\Filament\Resources\CarrotTasks\CarrotTaskResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCarrotTask extends ViewRecord
{
    protected static string $resource = CarrotTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\CarrotTasks\Pages;

use App\Filament\Resources\CarrotTasks\CarrotTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCarrotTask extends EditRecord
{
    protected static string $resource = CarrotTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\DriftBottles\Pages;

use App\Filament\Resources\DriftBottles\DriftBottleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriftBottles extends ListRecords
{
    protected static string $resource = DriftBottleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

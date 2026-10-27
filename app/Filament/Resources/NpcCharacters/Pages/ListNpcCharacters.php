<?php

namespace App\Filament\Resources\NpcCharacters\Pages;

use App\Filament\Resources\NpcCharacters\NpcCharacterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNpcCharacters extends ListRecords
{
    protected static string $resource = NpcCharacterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

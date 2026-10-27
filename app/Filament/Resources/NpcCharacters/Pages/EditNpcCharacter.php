<?php

namespace App\Filament\Resources\NpcCharacters\Pages;

use App\Filament\Resources\NpcCharacters\NpcCharacterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNpcCharacter extends EditRecord
{
    protected static string $resource = NpcCharacterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

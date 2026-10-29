<?php

namespace App\Filament\Resources\AuthorizationCodes\Pages;

use App\Filament\Resources\AuthorizationCodes\AuthorizationCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuthorizationCode extends EditRecord
{
    protected static string $resource = AuthorizationCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

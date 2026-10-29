<?php

namespace App\Filament\Resources\AuthorizationCodes\Pages;

use App\Filament\Resources\AuthorizationCodes\AuthorizationCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuthorizationCodes extends ListRecords
{
    protected static string $resource = AuthorizationCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

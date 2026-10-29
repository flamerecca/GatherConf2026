<?php

namespace App\Filament\Resources\AuthorizationCodes\Pages;

use App\Filament\Resources\AuthorizationCodes\AuthorizationCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthorizationCode extends CreateRecord
{
    protected static string $resource = AuthorizationCodeResource::class;
}

<?php

namespace App\Filament\Resources\PersonalityTypes\Pages;

use App\Filament\Resources\PersonalityTypes\PersonalityTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditPersonalityType extends EditRecord
{
    protected static string $resource = PersonalityTypeResource::class;
}

<?php

namespace App\Filament\Resources\PersonalityTypes\Pages;

use App\Filament\Resources\PersonalityTypes\PersonalityTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListPersonalityTypes extends ListRecords
{
    protected static string $resource = PersonalityTypeResource::class;
}

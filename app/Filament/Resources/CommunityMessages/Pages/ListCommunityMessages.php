<?php

namespace App\Filament\Resources\CommunityMessages\Pages;

use App\Filament\Resources\CommunityMessages\CommunityMessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityMessages extends ListRecords
{
    protected static string $resource = CommunityMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

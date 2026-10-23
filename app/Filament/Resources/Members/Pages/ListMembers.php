<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Widgets\BbtiDistributionChart;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            BbtiDistributionChart::class,
        ];
    }
}

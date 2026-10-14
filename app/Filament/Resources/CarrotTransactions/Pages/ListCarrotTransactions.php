<?php

namespace App\Filament\Resources\CarrotTransactions\Pages;

use App\Filament\Resources\CarrotTransactions\CarrotTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListCarrotTransactions extends ListRecords
{
    protected static string $resource = CarrotTransactionResource::class;
}

<?php

namespace App\Filament\Resources\Friendships\Pages;

use App\Filament\Resources\Friendships\FriendshipResource;
use Filament\Resources\Pages\ListRecords;

class ListFriendships extends ListRecords
{
    protected static string $resource = FriendshipResource::class;
}

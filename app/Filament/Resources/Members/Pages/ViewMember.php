<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\Actions\MemberActions;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            MemberActions::deactivate(),
            MemberActions::activate(),
            MemberActions::markVerified(),
            MemberActions::sendLoginLink(),
        ];
    }
}

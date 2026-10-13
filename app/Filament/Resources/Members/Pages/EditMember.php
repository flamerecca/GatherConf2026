<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\Actions\MemberActions;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            MemberActions::deactivate(),
            MemberActions::activate(),
            MemberActions::markVerified(),
        ];
    }
}

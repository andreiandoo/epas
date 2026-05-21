<?php

namespace App\Filament\Tenant\Resources\TenantTeamMemberResource\Pages;

use App\Filament\Tenant\Resources\TenantTeamMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantTeamMembers extends ListRecords
{
    protected static string $resource = TenantTeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}

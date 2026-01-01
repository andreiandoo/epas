<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrganizer extends CreateRecord
{
    protected static string $resource = OrganizerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $data['marketplace_client_id'] = $marketplaceAdmin->marketplace_client_id;

        return $data;
    }
}

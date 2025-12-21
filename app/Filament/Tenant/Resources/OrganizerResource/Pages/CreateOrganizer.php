<?php

namespace App\Filament\Tenant\Resources\OrganizerResource\Pages;

use App\Filament\Tenant\Resources\OrganizerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizer extends CreateRecord
{
    protected static string $resource = OrganizerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = auth()->user()->tenant;

        $data['tenant_id'] = $tenant->id;
        $data['payout_currency'] = $data['payout_currency'] ?? $tenant->currency ?? 'RON';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Domain;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        // Get the new_domains from the form data
        $newDomains = $this->data['new_domains'] ?? [];

        if (!empty($newDomains)) {
            foreach ($newDomains as $domainData) {
                if (!empty($domainData['domain'])) {
                    Domain::create([
                        'tenant_id' => $this->record->id,
                        'domain' => $domainData['domain'],
                        'is_primary' => $domainData['is_primary'] ?? false,
                        'is_active' => true,
                        'is_verified' => false,
                    ]);
                }
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove new_domains from the data as it's not a column on tenants table
        unset($data['new_domains']);

        return $data;
    }
}

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
        // Mirror the primary entry from the new_domains repeater into
        // the legacy tenants.domain column so older code paths that
        // still read it (ClientEarnings, MarketplaceClient
        // ConfigController, etc.) keep working. Falls back to the
        // first entry if no primary is flagged. After the column was
        // made nullable, this is best-effort: a tenant created with
        // no new_domains entries simply gets domain=null.
        $newDomains = $data['new_domains'] ?? [];
        if (!empty($newDomains)) {
            $primary = collect($newDomains)
                ->first(fn ($d) => !empty($d['is_primary']) && !empty($d['domain']));
            $first = collect($newDomains)
                ->first(fn ($d) => !empty($d['domain']));
            $data['domain'] = $primary['domain'] ?? $first['domain'] ?? null;
        } else {
            $data['domain'] = null;
        }

        // Remove new_domains from the data as it's not a column on tenants table
        unset($data['new_domains']);

        return $data;
    }
}

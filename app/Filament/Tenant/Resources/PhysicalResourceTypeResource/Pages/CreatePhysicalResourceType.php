<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceTypeResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceTypeResource;
use App\Models\Leisure\PhysicalResourceType;
use Filament\Resources\Pages\CreateRecord;

class CreatePhysicalResourceType extends CreateRecord
{
    protected static string $resource = PhysicalResourceTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = auth()->user()?->tenant?->id;
        $data['tenant_id'] = $tenantId;
        if (empty($data['slug'])) {
            $data['slug'] = PhysicalResourceType::generateSlug($data['name'] ?? 'res', $tenantId);
        }
        return $data;
    }
}

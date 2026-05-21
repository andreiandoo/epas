<?php

namespace App\Filament\Tenant\Resources\PhysicalResourceResource\Pages;

use App\Filament\Tenant\Resources\PhysicalResourceResource;
use App\Models\Leisure\PhysicalResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhysicalResource extends CreateRecord
{
    protected static string $resource = PhysicalResourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = auth()->user()?->tenant?->id;
        $data['tenant_id'] = $tenantId;
        if (empty($data['qr_code'])) {
            // Retry a few times to avoid the unlikely collision on the unique index.
            for ($i = 0; $i < 5; $i++) {
                $code = PhysicalResource::generateQrCode($tenantId, $data['resource_type'] ?? 'res');
                if (! PhysicalResource::where('qr_code', $code)->exists()) {
                    $data['qr_code'] = $code;
                    break;
                }
            }
        }
        return $data;
    }
}

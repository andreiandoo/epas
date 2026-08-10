<?php

namespace App\Filament\Tenant\Resources\ShortResource\Pages;

use App\Filament\Tenant\Resources\ShortResource;
use App\Models\Short;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateShort extends CreateRecord
{
    protected static string $resource = ShortResource::class;

    /**
     * Stamp the tenant and route the short through central moderation.
     *
     * Both are set here rather than in the form: an organiser must not be able
     * to publish straight into the global feed, or to file a short under someone
     * else's tenant, by editing the payload.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant_id;
        $data['status'] = Short::STATUS_PENDING_REVIEW;
        $data['owner_type'] = $data['owner_type'] ?? Tenant::class;
        $data['owner_id'] = $data['owner_id'] ?? auth()->user()?->tenant_id;

        return $data;
    }
}

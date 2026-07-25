<?php

namespace App\Filament\Tenant\Resources\SubscriptionPlanResource\Pages;

use App\Filament\Tenant\Resources\SubscriptionPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant?->id;
        return $data;
    }
}

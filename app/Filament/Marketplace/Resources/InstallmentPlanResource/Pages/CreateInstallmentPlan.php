<?php

namespace App\Filament\Marketplace\Resources\InstallmentPlanResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\InstallmentPlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateInstallmentPlan extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = InstallmentPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        if (empty($data['slug'])) {
            $name = is_array($data['name'] ?? null) ? ($data['name']['ro'] ?? $data['name']['en'] ?? 'plan') : 'plan';
            $data['slug'] = Str::slug($name) . '-' . Str::lower(Str::random(4));
        }

        if (($data['plan_type'] ?? null) === 'bnpl_single') {
            $data['number_of_installments'] = 1;
        }

        return $data;
    }
}

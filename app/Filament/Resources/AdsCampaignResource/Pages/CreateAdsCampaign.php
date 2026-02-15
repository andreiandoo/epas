<?php

namespace App\Filament\Resources\AdsCampaignResource\Pages;

use App\Filament\Resources\AdsCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdsCampaign extends CreateRecord
{
    protected static string $resource = AdsCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Auto-generate UTM campaign slug if empty
        if (empty($data['utm_campaign']) && !empty($data['name'])) {
            $data['utm_campaign'] = \Illuminate\Support\Str::slug($data['name']);
        }

        return $data;
    }
}

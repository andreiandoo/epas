<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TaxTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTaxTemplate extends CreateRecord
{
    protected static string $resource = TaxTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $data['marketplace_client_id'] = $marketplaceAdmin?->marketplace_client_id;

        // When source mode is ON, use the raw HTML from textarea (preserves inline CSS)
        if (!empty($data['page1_source_mode']) && isset($data['html_content_source'])) {
            $data['html_content'] = $data['html_content_source'];
        }
        if (!empty($data['page2_source_mode']) && isset($data['html_content_page_2_source'])) {
            $data['html_content_page_2'] = $data['html_content_page_2_source'];
        }

        unset(
            $data['page1_source_mode'],
            $data['page2_source_mode'],
            $data['html_content_source'],
            $data['html_content_page_2_source'],
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateEvent extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplace = static::getMarketplaceClient();

        $data['marketplace_client_id'] = $marketplace?->id;

        // Auto-fill ticket_terms from tenant settings if empty
        // The form uses translatable format: ticket_terms.{language}
        // If ticket_terms array is empty but tenant has default terms, populate it
        if ($marketplace?->ticket_terms) {
            $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';
            if (empty($data['ticket_terms'][$marketplaceLanguage])) {
                $data['ticket_terms'][$marketplaceLanguage] = $marketplace->ticket_terms;
            }
        }

        return $data;
    }
}

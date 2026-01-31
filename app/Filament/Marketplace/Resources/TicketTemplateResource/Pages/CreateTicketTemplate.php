<?php

namespace App\Filament\Marketplace\Resources\TicketTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateTicketTemplate extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = TicketTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['version'] = 1;

        return $data;
    }
}

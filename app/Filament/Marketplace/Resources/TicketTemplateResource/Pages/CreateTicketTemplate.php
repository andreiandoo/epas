<?php

namespace App\Filament\Marketplace\Resources\TicketTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketTemplate extends CreateRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        $data['version'] = 1;

        return $data;
    }
}

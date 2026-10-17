<?php

namespace App\Filament\Tenant\Resources\NewsletterResource\Pages;

use App\Filament\Tenant\Resources\NewsletterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';
        return $data;
    }

    // După salvare, pe pagina campaniei: de acolo se trimite testul și campania
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

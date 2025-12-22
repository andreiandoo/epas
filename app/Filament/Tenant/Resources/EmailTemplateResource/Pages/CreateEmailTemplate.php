<?php

namespace App\Filament\Tenant\Resources\EmailTemplateResource\Pages;

use App\Filament\Tenant\Resources\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = filament()->getTenant()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

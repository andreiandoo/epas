<?php

namespace App\Filament\Marketplace\Resources\ActivityLocationResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityLocation extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ActivityLocationResource::class;

    /** The marketplace always comes from the session, never from the form. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        if (in_array($data['review_status'] ?? null, ['approved', 'rejected'], true)) {
            $data['reviewed_at'] = now();
            $data['reviewed_by'] = auth()->id();
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

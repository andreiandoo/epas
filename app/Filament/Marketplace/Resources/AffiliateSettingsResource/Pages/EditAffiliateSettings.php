<?php

namespace App\Filament\Marketplace\Resources\AffiliateSettingsResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateSettings extends EditRecord
{
    protected static string $resource = AffiliateSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No delete action - settings should always exist
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Affiliate settings saved';
    }
}

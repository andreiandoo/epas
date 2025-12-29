<?php

namespace App\Filament\Marketplace\Resources\AffiliateSettingsResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateSettingsResource;
use App\Models\AffiliateSettings;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliateSettings extends ListRecords
{
    protected static string $resource = AffiliateSettingsResource::class;

    public function mount(): void
    {
        parent::mount();

        // Auto-create settings if not exists and redirect to edit
        $marketplace = static::getMarketplaceClient();

        if ($marketplace) {
            $settings = AffiliateSettings::where('marketplace_client_id', $marketplace->id)->first();

            if (!$settings) {
                $settings = AffiliateSettings::getOrCreate($marketplace->id);
            }

            // Redirect to edit page
            $this->redirect(AffiliateSettingsResource::getUrl('edit', ['record' => $settings]));
        }
    }

    protected function getTableQuery(): ?Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getTableQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

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
        $tenant = filament()->getTenant();

        if ($tenant) {
            $settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

            if (!$settings) {
                $settings = AffiliateSettings::getOrCreate($tenant->id);
            }

            // Redirect to edit page
            $this->redirect(AffiliateSettingsResource::getUrl('edit', ['record' => $settings]));
        }
    }

    protected function getTableQuery(): ?Builder
    {
        $tenant = filament()->getTenant();

        return parent::getTableQuery()
            ->where('tenant_id', $tenant?->id);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

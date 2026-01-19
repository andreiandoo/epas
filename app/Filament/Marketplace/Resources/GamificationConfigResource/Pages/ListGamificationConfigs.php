<?php

namespace App\Filament\Marketplace\Resources\GamificationConfigResource\Pages;

use App\Filament\Marketplace\Resources\GamificationConfigResource;
use App\Models\Gamification\GamificationConfig;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class ListGamificationConfigs extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = GamificationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        // Auto-create config if it doesn't exist and redirect to edit
        $marketplace = static::getMarketplaceClient();
        if ($marketplace) {
            $config = GamificationConfig::getOrCreateForMarketplace($marketplace->id);
            $this->redirect(GamificationConfigResource::getUrl('edit', ['record' => $config]));
            return;
        }

        parent::mount();
    }
}

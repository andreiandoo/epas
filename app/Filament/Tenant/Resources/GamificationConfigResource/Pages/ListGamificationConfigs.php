<?php

namespace App\Filament\Tenant\Resources\GamificationConfigResource\Pages;

use App\Filament\Tenant\Resources\GamificationConfigResource;
use App\Models\Gamification\GamificationConfig;
use Filament\Resources\Pages\ListRecords;

class ListGamificationConfigs extends ListRecords
{
    protected static string $resource = GamificationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        // Auto-create config if it doesn't exist and redirect to edit
        $tenant = auth()->user()->tenant;
        if ($tenant) {
            $config = GamificationConfig::getOrCreateForTenant($tenant->id);
            $this->redirect(GamificationConfigResource::getUrl('edit', ['record' => $config]));
            return;
        }

        parent::mount();
    }
}

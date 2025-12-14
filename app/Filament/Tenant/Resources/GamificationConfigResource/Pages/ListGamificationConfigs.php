<?php

namespace App\Filament\Tenant\Resources\GamificationConfigResource\Pages;

use App\Filament\Tenant\Resources\GamificationConfigResource;
use App\Models\Gamification\GamificationConfig;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGamificationConfigs extends ListRecords
{
    protected static string $resource = GamificationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => !GamificationConfig::where('tenant_id', auth()->user()->tenant?->id)->exists()),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        // Auto-redirect to create if no config exists
        $tenant = auth()->user()->tenant;
        if ($tenant) {
            $config = GamificationConfig::where('tenant_id', $tenant->id)->first();
            if (!$config) {
                $this->redirect(GamificationConfigResource::getUrl('create'));
            } elseif ($config) {
                $this->redirect(GamificationConfigResource::getUrl('edit', ['record' => $config]));
            }
        }
    }
}

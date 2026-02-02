<?php

namespace App\Filament\Marketplace\Resources\ExperienceConfigResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ExperienceConfigResource;
use App\Models\Gamification\ExperienceConfig;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExperienceConfigs extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = ExperienceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        // Auto-redirect to edit if config exists, or create if not
        $marketplaceClientId = static::getMarketplaceClientId();
        if ($marketplaceClientId) {
            $config = ExperienceConfig::where('marketplace_client_id', $marketplaceClientId)->first();
            if ($config) {
                $this->redirect(ExperienceConfigResource::getUrl('edit', ['record' => $config]));
            } else {
                $this->redirect(ExperienceConfigResource::getUrl('create'));
            }
        }
    }
}

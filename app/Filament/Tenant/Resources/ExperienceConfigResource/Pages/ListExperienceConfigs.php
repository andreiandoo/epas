<?php

namespace App\Filament\Tenant\Resources\ExperienceConfigResource\Pages;

use App\Filament\Tenant\Resources\ExperienceConfigResource;
use App\Models\Gamification\ExperienceConfig;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExperienceConfigs extends ListRecords
{
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
        $tenant = auth()->user()->tenant;
        if ($tenant) {
            $config = ExperienceConfig::where('tenant_id', $tenant->id)->first();
            if ($config) {
                $this->redirect(ExperienceConfigResource::getUrl('edit', ['record' => $config]));
            } else {
                $this->redirect(ExperienceConfigResource::getUrl('create'));
            }
        }
    }
}

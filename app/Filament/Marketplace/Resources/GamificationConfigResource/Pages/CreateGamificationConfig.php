<?php

namespace App\Filament\Marketplace\Resources\GamificationConfigResource\Pages;

use App\Filament\Marketplace\Resources\GamificationConfigResource;
use App\Models\Gamification\GamificationAction;
use Filament\Resources\Pages\CreateRecord;

class CreateGamificationConfig extends CreateRecord
{
    protected static string $resource = GamificationConfigResource::class;

    protected function afterCreate(): void
    {
        // Seed default actions for this tenant
        GamificationAction::seedDefaultActions($this->record->tenant_id);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

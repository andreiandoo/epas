<?php

namespace App\Filament\Marketplace\Resources\GamificationConfigResource\Pages;

use App\Filament\Marketplace\Resources\GamificationConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGamificationConfig extends EditRecord
{
    protected static string $resource = GamificationConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Don't allow delete - config should always exist
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources\ExperienceActionResource\Pages;

use App\Filament\Marketplace\Resources\ExperienceActionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExperienceAction extends EditRecord
{
    protected static string $resource = ExperienceActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

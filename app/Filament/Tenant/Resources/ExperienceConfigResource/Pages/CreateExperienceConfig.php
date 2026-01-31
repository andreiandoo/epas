<?php

namespace App\Filament\Tenant\Resources\ExperienceConfigResource\Pages;

use App\Filament\Tenant\Resources\ExperienceConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExperienceConfig extends CreateRecord
{
    protected static string $resource = ExperienceConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

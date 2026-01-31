<?php

namespace App\Filament\Tenant\Resources\ExperienceConfigResource\Pages;

use App\Filament\Tenant\Resources\ExperienceConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExperienceConfig extends EditRecord
{
    protected static string $resource = ExperienceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources\ExperienceConfigResource\Pages;

use App\Filament\Marketplace\Resources\ExperienceConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExperienceConfig extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ExperienceConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

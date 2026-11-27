<?php

namespace App\Filament\Marketplace\Resources\ExperienceActionResource\Pages;

use App\Filament\Marketplace\Resources\ExperienceActionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExperienceAction extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ExperienceActionResource::class;
}

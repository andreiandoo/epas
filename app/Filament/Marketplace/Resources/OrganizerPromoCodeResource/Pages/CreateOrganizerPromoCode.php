<?php

namespace App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizerPromoCode extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = OrganizerPromoCodeResource::class;
}

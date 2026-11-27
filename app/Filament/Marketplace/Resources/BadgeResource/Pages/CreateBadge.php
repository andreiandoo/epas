<?php

namespace App\Filament\Marketplace\Resources\BadgeResource\Pages;

use App\Filament\Marketplace\Resources\BadgeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBadge extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = BadgeResource::class;
}

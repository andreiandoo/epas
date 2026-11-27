<?php

namespace App\Filament\Marketplace\Resources\EmailLogResource\Pages;

use App\Filament\Marketplace\Resources\EmailLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailLog extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = EmailLogResource::class;
}

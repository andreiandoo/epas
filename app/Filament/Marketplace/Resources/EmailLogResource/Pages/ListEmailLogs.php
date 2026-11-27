<?php

namespace App\Filament\Marketplace\Resources\EmailLogResource\Pages;

use App\Filament\Marketplace\Resources\EmailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailLogs extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = EmailLogResource::class;
}

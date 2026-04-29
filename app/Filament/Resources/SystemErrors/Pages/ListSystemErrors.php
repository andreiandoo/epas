<?php

namespace App\Filament\Resources\SystemErrors\Pages;

use App\Filament\Resources\SystemErrors\SystemErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListSystemErrors extends ListRecords
{
    protected static string $resource = SystemErrorResource::class;
}

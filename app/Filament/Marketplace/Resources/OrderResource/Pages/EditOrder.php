<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = OrderResource::class;
}

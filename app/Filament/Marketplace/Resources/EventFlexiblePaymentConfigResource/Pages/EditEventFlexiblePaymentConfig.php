<?php

namespace App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource\Pages;

use App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventFlexiblePaymentConfig extends EditRecord
{
    protected static string $resource = EventFlexiblePaymentConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}

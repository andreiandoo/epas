<?php

namespace App\Filament\Marketplace\Resources\GroupBookingResource\Pages;

use App\Filament\Marketplace\Resources\GroupBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGroupBooking extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = GroupBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

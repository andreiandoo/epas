<?php

namespace App\Filament\Marketplace\Resources\GroupBookingResource\Pages;

use App\Filament\Marketplace\Resources\GroupBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupBookings extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = GroupBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

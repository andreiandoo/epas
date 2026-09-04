<?php

namespace App\Filament\Marketplace\Resources\OrderInsuranceResource\Pages;

use App\Filament\Marketplace\Resources\OrderInsuranceResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderInsurance extends ListRecords
{
    protected static string $resource = OrderInsuranceResource::class;

    public function getTitle(): string
    {
        return 'Comenzi cu asigurare bilete';
    }

    public function getSubheading(): ?string
    {
        return 'Comenzi în care clientul a cumpărat asigurare (bilete rambursabile). Suma asigurării revine asigurătorului, nu organizatorului.';
    }
}

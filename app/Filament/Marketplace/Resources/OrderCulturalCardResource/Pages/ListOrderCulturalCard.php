<?php

namespace App\Filament\Marketplace\Resources\OrderCulturalCardResource\Pages;

use App\Filament\Marketplace\Resources\OrderCulturalCardResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderCulturalCard extends ListRecords
{
    protected static string $resource = OrderCulturalCardResource::class;

    public function getTitle(): string
    {
        return 'Comenzi cu Card Cultural Național';
    }

    public function getSubheading(): ?string
    {
        return 'Comenzi plătite via card cultural. Surcharge-ul Netopia (4%) revine AmBilet, nu organizatorului — AmBilet îl transferă mai departe către furnizorul de plată card cultural.';
    }
}

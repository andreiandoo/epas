<?php

namespace App\Filament\Marketplace\Resources\AffiliateConversionResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateConversionResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateConversions extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = AffiliateConversionResource::class;
}

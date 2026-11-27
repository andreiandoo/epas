<?php

namespace App\Filament\Marketplace\Resources\AffiliateWithdrawalResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateWithdrawalResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateWithdrawals extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = AffiliateWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

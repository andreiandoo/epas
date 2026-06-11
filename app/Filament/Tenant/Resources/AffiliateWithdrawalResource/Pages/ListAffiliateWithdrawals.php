<?php

namespace App\Filament\Tenant\Resources\AffiliateWithdrawalResource\Pages;

use App\Filament\Tenant\Resources\AffiliateWithdrawalResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateWithdrawals extends ListRecords
{
    protected static string $resource = AffiliateWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

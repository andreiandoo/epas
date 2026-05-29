<?php

namespace App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;

use App\Filament\Marketplace\Resources\PendingPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingPayments extends ListRecords
{
    protected static string $resource = PendingPaymentResource::class;

    public function getTitle(): string
    {
        return 'De plătit';
    }

    public function getHeading(): string
    {
        return 'De plătit';
    }

    public function getSubheading(): ?string
    {
        return 'Deconturile care necesită transfer către organizatori. Filtrul implicit afișează doar plățile în așteptare.';
    }
}

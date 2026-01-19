<?php
namespace App\Filament\Resources\Billing\InvoiceResource\Pages;

use App\Filament\Resources\Billing\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [ \Filament\Actions\CreateAction::make() ];
    }
}

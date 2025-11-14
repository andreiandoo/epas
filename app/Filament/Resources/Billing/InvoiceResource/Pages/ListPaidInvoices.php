<?php

namespace App\Filament\Resources\Billing\InvoiceResource\Pages;

use App\Filament\Resources\Billing\InvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListPaidInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
    protected static ?string $title = 'Paid Invoices';

    protected function getTableQuery(): Builder|Relation|null
    {
        $query = parent::getTableQuery();

        return $query?->where('status', 'paid');
    }
}

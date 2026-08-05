<?php

namespace App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOrganizerInvoices extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = OrganizerInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        // Bulk "Generează Facturi" was removed on 2026-08-04 after it
        // silently emitted every organizer invoice on the marketplace
        // when clicked once. The correct flow is per-decont via
        // ViewPayout::generate_invoice_organizer, which knows the exact
        // breakdown (POS + online-included + refunded − kept) for the
        // specific payout / event. See commit that introduced this
        // change for the incident context.
        return [];
    }
}

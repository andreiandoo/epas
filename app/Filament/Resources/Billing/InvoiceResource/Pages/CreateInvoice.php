<?php

namespace App\Filament\Resources\Billing\InvoiceResource\Pages;

use App\Filament\Resources\Billing\InvoiceResource;
use App\Models\Setting;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected string $view = 'filament.resources.billing.invoice.pages.create-invoice';

    public function getSettings()
    {
        return Setting::current();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate invoice number if not provided
        if (empty($data['number'])) {
            $settings = Setting::current();
            $data['number'] = $settings->getNextInvoiceNumber();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Trigger invoice created email automatically (handled by InvoiceObserver)
    }
}

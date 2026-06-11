<?php

namespace App\Services\EFactura;

use App\Models\Invoice;

/**
 * Transforms Invoice model data into the format expected by AnafAdapter::buildXml()
 */
class InvoiceEFacturaTransformer
{
    /**
     * Transform an Invoice model to eFactura data format
     */
    public function transform(Invoice $invoice): array
    {
        $meta = $invoice->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];

        return [
            'invoice_number' => $invoice->number,
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'seller' => $this->transformParty($issuer),
            'buyer' => $this->transformParty($client),
            'lines' => $this->transformLines($items),
            'total' => (float) $invoice->subtotal,
            'vat_total' => (float) $invoice->vat_amount,
            'grand_total' => (float) $invoice->amount,
        ];
    }

    /**
     * Transform party data (issuer or client) from invoice meta
     */
    protected function transformParty(array $party): array
    {
        $address = [];
        if (!empty($party['address'])) {
            // Address can be a string or structured array
            if (is_string($party['address'])) {
                $address = ['street' => $party['address'], 'city' => '', 'country' => 'RO'];
            } else {
                $address = $party['address'];
            }
        }

        // Ensure country defaults to RO
        if (empty($address['country'])) {
            $address['country'] = 'RO';
        }

        return [
            'name' => $party['name'] ?? '',
            'vat_number' => $party['cui'] ?? $party['vat_number'] ?? '',
            'reg_number' => $party['reg_com'] ?? $party['reg_number'] ?? '',
            'address' => $address,
        ];
    }

    /**
     * Transform line items from invoice meta
     */
    protected function transformLines(array $items): array
    {
        return array_map(function ($item) {
            return [
                'description' => $item['description'] ?? '',
                'name' => $item['name'] ?? $item['description'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['price'] ?? $item['unit_price'] ?? 0),
            ];
        }, $items);
    }

    /**
     * Validate that an invoice has sufficient data for eFactura submission
     */
    public function validate(Invoice $invoice): array
    {
        $errors = [];
        $meta = $invoice->meta ?? [];
        $issuer = $meta['issuer'] ?? [];
        $client = $meta['client'] ?? [];
        $items = $meta['items'] ?? [];

        if (empty($invoice->number)) {
            $errors[] = 'Numărul facturii lipsește';
        }

        if (empty($issuer['name'])) {
            $errors[] = 'Numele emitentului lipsește';
        }

        if (empty($issuer['cui'])) {
            $errors[] = 'CUI-ul emitentului lipsește';
        }

        if (empty($client['name'])) {
            $errors[] = 'Numele clientului lipsește';
        }

        if (empty($items)) {
            $errors[] = 'Factura nu are linii/produse';
        }

        return $errors;
    }
}

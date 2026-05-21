<?php

namespace App\Services\Leisure;

use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Renders an 80mm-wide receipt as HTML+CSS suited for browser-based thermal
 * printing (operator's default printer is set to the thermal device; the
 * browser's print dialog handles ESC/POS conversion).
 *
 * Returns a Blade view rendering — caller is responsible for the final
 * response (typically an inline page that calls window.print() on load).
 *
 * Hardware MVP per the design doc: zero device drivers, zero WebUSB.
 */
class ReceiptPrinterService
{
    public function renderReceipt(Order $order, array $extra = []): View
    {
        return view('leisure.receipt', [
            'order' => $order,
            'tenant' => $order->tenant,
            'taxRegistry' => $extra['tax_registry'] ?? null,
            'items' => $extra['items'] ?? ($order->items ?? collect()),
            'printedAt' => now(),
            'channelLabel' => $extra['channel_label'] ?? '—',
            'paymentMethod' => $extra['payment_method'] ?? 'cash',
        ]);
    }
}

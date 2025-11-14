<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function download(Invoice $invoice)
    {
        $settings = Setting::current();

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'settings' => $settings,
        ]);

        // Format: Tixello-invoice-seriesinumar.pdf
        // Extract series and number from invoice number (e.g., "INV-2024-000001" -> "2024000001")
        $seriesNumber = str_replace(['-', '_'], '', preg_replace('/^[A-Z]+[-_]/', '', $invoice->number));

        return $pdf->download("Tixello-invoice-{$seriesNumber}.pdf");
    }

    public function preview(Invoice $invoice)
    {
        $settings = Setting::current();

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'settings' => $settings,
        ]);

        // Format: Tixello-invoice-seriesinumar.pdf
        // Extract series and number from invoice number (e.g., "INV-2024-000001" -> "2024000001")
        $seriesNumber = str_replace(['-', '_'], '', preg_replace('/^[A-Z]+[-_]/', '', $invoice->number));

        return $pdf->stream("Tixello-invoice-{$seriesNumber}.pdf");
    }
}

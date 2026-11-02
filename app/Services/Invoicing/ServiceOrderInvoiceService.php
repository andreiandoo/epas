<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\MarketplaceClient;
use App\Models\ServiceOrder;
use App\Services\Accounting\AccountingService;
use App\Services\MarketplaceEmailService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Factura serviciilor extra, emisă automat după ce plata e marcată „paid”:
 * factura locală, contabilitate (seria din conector), SPV, email organizator.
 * Fiecare pas e idempotent, deci jobul poate fi reluat.
 * Se activează din Setări, per marketplace.
 */
class ServiceOrderInvoiceService
{
    public function isEnabled(?MarketplaceClient $client): bool
    {
        return $client !== null && (bool) data_get($client->settings, 'service_order_invoicing.enabled', false);
    }

    /** Aruncă la eroare, jobul reîncearcă. */
    public function process(ServiceOrder $order): void
    {
        $client = $order->marketplaceClient;
        if (!$this->isEnabled($client) || $order->payment_status !== ServiceOrder::PAYMENT_PAID) {
            return;
        }

        $accounting = app(AccountingService::class);
        $connector = $accounting->marketplaceConnector($client->id);
        if (!$connector) {
            return;
        }

        $invoice = $order->issueVatInvoice();
        if (!$invoice) {
            return;
        }

        $meta = $invoice->meta ?? [];

        // Fără nume sau CUI/CNP factura rămâne pe pagina ei: după completarea
        // datelor organizatorului se trimite manual de acolo.
        if (empty($meta['accounting']['external_ref']) && (empty($meta['client']['name']) || empty($meta['client']['cui']))) {
            if (empty($meta['accounting_blocked_at'])) {
                $meta['accounting_blocked_at'] = now()->toIso8601String();
                $invoice->update(['meta' => $meta]);
                $this->alertAdmin($order, 'Organizatorul nu are datele de facturare complete (nume și CUI/CNP).');
            }

            return;
        }

        // 1. Contabilitate
        if (empty($meta['accounting']['external_ref'])) {
            $result = $accounting->issueMarketplaceInvoice($client->id, $invoice->number, $this->payload($invoice, $client, $order, $connector['draft']));
            if (empty($result['success']) || empty($result['external_ref'])) {
                throw new \RuntimeException('Contabilitatea nu a emis factura.');
            }
            $meta['accounting'] = [
                'external_ref' => $result['external_ref'],
                'invoice_number' => $result['invoice_number'],
                'doc_type' => 'invoice',
                'provider' => $connector['provider'],
                'is_draft' => $connector['draft'],
                'sent_at' => now()->toIso8601String(),
            ];
            unset($meta['accounting_blocked_at']);
            $invoice->update(['meta' => $meta]);
        }

        // Ciorna nu merge în SPV și nu se trimite organizatorului
        if (!empty($meta['accounting']['is_draft'])) {
            return;
        }

        // 2. SPV. O eroare aici nu oprește trimiterea către organizator.
        if (empty($meta['accounting']['efactura_sent_at'])) {
            try {
                $spv = $accounting->submitMarketplaceEFactura($client->id, $meta['accounting']['external_ref']);
                if (!empty($spv['supported'])) {
                    $meta['accounting']['efactura_sent_at'] = now()->toIso8601String();
                    unset($meta['accounting']['efactura_error']);
                    $invoice->update(['meta' => $meta]);
                }
            } catch (\Throwable $e) {
                $meta['accounting']['efactura_error'] = mb_substr($e->getMessage(), 0, 500);
                $invoice->update(['meta' => $meta]);
                Log::warning('[ServiceOrderInvoice] e-Factura submit failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            }
        }

        // 3. Organizatorul primește factura
        if (empty($meta['accounting']['organizer_sent_at'])) {
            $this->sendToOrganizer($order, $invoice, $client, $meta);
            $meta['accounting']['organizer_sent_at'] = now()->toIso8601String();
            $invoice->update(['meta' => $meta]);
            $order->notifyInvoiceGenerated();
        }
    }

    protected function payload(Invoice $invoice, MarketplaceClient $client, ServiceOrder $order, bool $draft): array
    {
        $meta = $invoice->meta ?? [];
        $organizer = $order->marketplaceOrganizer;

        $payload = [
            'seller_vat' => $client->cui ?? '',
            'issue_date' => $invoice->issue_date?->format('Y-m-d') ?? date('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d') ?? date('Y-m-d'),
            'currency' => $invoice->currency ?? 'RON',
            'number' => $invoice->number,
            'is_draft' => $draft,
            'doc_type' => 'invoice',
            'customer' => [
                'name' => $meta['client']['name'] ?? '',
                'vat_number' => $meta['client']['cui'] ?? '',
                'reg_number' => $meta['client']['reg_com'] ?? '',
                'email' => $organizer?->billing_email ?? $organizer?->email ?? '',
                'address' => [
                    'street' => $meta['client']['street'] ?? ($meta['client']['address'] ?? ''),
                    'city' => $meta['client']['city'] ?? '',
                    'county' => $meta['client']['county'] ?? '',
                    'country' => 'Romania',
                ],
            ],
            'lines' => array_map(fn ($item) => [
                'product_name' => $item['name'] ?? 'Servicii promovare',
                'description' => $item['description'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) ($item['vat_rate'] ?? 0),
                'vat_included' => false,
                'unit' => 'buc',
            ], $meta['items'] ?? []),
        ];

        // Serviciul e deja plătit: factura apare încasată în contabilitate
        if (!$draft) {
            $payload['collect'] = [
                'type' => $order->payment_method === 'card' ? 'Card' : 'Ordin de plata',
                'documentNumber' => (string) ($order->payment_reference ?: $order->order_number),
            ];
        }

        $preparer = trim((string) data_get($client->settings, 'invoice_preparer', ''));
        if ($preparer !== '') {
            $payload['issuer_name'] = $preparer;
            $payload['issuer_cnp'] = trim((string) data_get($client->settings, 'invoice_preparer_cnp', ''));
        }

        return $payload;
    }

    protected function sendToOrganizer(ServiceOrder $order, Invoice $invoice, MarketplaceClient $client, array &$meta): void
    {
        $organizer = $order->marketplaceOrganizer;
        $email = $organizer?->billing_email ?: $organizer?->email;
        if (!$email) {
            return;
        }

        $ref = $meta['accounting']['external_ref'];
        $url = $meta['accounting']['pdf_url'] ?? null;
        if (!$url) {
            $url = app(AccountingService::class)->getMarketplaceInvoicePdf($client->id, $ref, 'invoice')['pdf_url'] ?? null;
            if ($url) {
                $meta['accounting']['pdf_url'] = $url;
                $meta['accounting']['pdf_fetched_at'] = now()->toIso8601String();
                $invoice->update(['meta' => $meta]);
            }
        }

        $response = $url ? Http::timeout(20)->get($url) : null;
        if (!$response || !$response->successful() || !str_starts_with($response->body(), '%PDF')) {
            throw new \RuntimeException('PDF-ul facturii nu este încă disponibil.');
        }

        $number = $meta['accounting']['invoice_number'] ?? $invoice->number;
        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.5;">'
            . '<p>Bună ziua,</p>'
            . '<p>Atașat găsiți factura <strong>' . e($number) . '</strong> pentru comanda de servicii <strong>' . e($order->order_number) . '</strong>.</p>'
            . '<p>Cu respect,<br><strong>' . e($client->name) . '</strong></p>'
            . '</div>';

        app(CompanyInvoiceService::class)->sendEmail($client, $email, $organizer->name, 'Factura ' . $number . ' - ' . $order->order_number, $html, $response->body(), 'Factura-' . str_replace('/', '-', $ref) . '.pdf', [
            'marketplace_organizer_id' => $organizer->id,
            'template_slug' => 'invoice_send',
        ]);
    }

    /** Anunță operatorul când factura n-a putut fi emisă automat. */
    public function alertAdmin(ServiceOrder $order, string $reason): void
    {
        Log::error('[ServiceOrderInvoice] invoice needs attention', ['service_order_id' => $order->id, 'reason' => $reason]);

        $client = $order->marketplaceClient;
        if (!$client) {
            return;
        }

        try {
            (new MarketplaceEmailService($client))->sendAdminNotification(
                slug: 'admin_service_order_invoice_failed',
                settingKey: 'service_orders_email',
                variables: [
                    'service_order_number' => $order->order_number,
                    'reason' => $reason,
                    'view_url' => url("/marketplace/service-orders/{$order->id}"),
                ],
                fallbackSubject: 'Factură neemisă: {{service_order_number}}',
                fallbackHtml: '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937;">'
                    . '<p>Factura comenzii <strong>{{service_order_number}}</strong> nu a fost emisă automat.</p>'
                    . '<p>{{reason}}</p>'
                    . '<p><a href="{{view_url}}">Vezi comanda</a></p>'
                    . '</div>',
            );
        } catch (\Throwable $e) {
            Log::warning('[ServiceOrderInvoice] admin alert failed', ['service_order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}

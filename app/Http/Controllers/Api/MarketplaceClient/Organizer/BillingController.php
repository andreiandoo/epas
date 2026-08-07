<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Invoice;
use App\Models\MarketplaceOrganizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingController extends BaseController
{
    /**
     * Get invoices list with filtering
     */
    public function invoices(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // For now, return commission invoices based on completed orders
        // In a real implementation, this would be from an invoices table
        $status = $request->input('status');
        $perPage = min((int) $request->input('per_page', 10), 50);
        $page = (int) $request->input('page', 1);

        // Generate invoice data from organizer's order history
        $invoices = $this->getOrganizerInvoices($organizer, $status, $perPage, $page);

        // Calculate stats
        $stats = $this->getInvoiceStats($organizer);

        return $this->success([
            'invoices' => $invoices['data'],
            'total' => $invoices['total'],
            'stats' => $stats,
        ]);
    }

    /**
     * Export the organizer's invoices as a CSV download.
     *
     * Returns a real text/csv attachment (UTF-8 BOM for Excel) instead of the
     * previous behaviour where the route didn't exist and the proxy forwarded
     * a 404 HTML page. The ambilet proxy detects the CSV via the
     * Content-Disposition header + BOM and streams it as a download.
     */
    public function exportInvoices(Request $request): \Illuminate\Http\Response
    {
        $organizer = $this->requireOrganizer($request);

        $status = $request->input('status', 'all');

        // Pull all matching invoices (reuse the same source as the list view).
        $result = $this->getOrganizerInvoices($organizer, $status, 100000, 1);
        $invoices = $result['data'] ?? [];

        $currency = $organizer->marketplaceClient?->currency ?? 'RON';

        $statusLabels = [
            'paid' => 'Plătită',
            'pending' => 'În așteptare',
            'outstanding' => 'În așteptare',
            'cancelled' => 'Anulată',
            'refunded' => 'Stornată',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['Număr', 'Data', 'Descriere', 'Sumă', 'Monedă', 'Status']);

        foreach ($invoices as $inv) {
            fputcsv($fh, [
                $inv['number'] ?? '',
                $inv['date'] ?? '',
                $inv['description'] ?? '',
                number_format((float) ($inv['amount'] ?? 0), 2, '.', ''),
                $currency,
                $statusLabels[$inv['status'] ?? ''] ?? ($inv['status'] ?? ''),
            ]);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        // UTF-8 BOM so Excel renders diacritics correctly (and the ambilet
        // proxy recognises the payload as CSV).
        $csv = "\xEF\xBB\xBF" . $csv;

        $filename = 'facturi-' . $organizer->id . '-' . Carbon::now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get single invoice detail
     */
    public function showInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Get invoice details (mock implementation)
        $invoice = $this->getInvoiceDetail($organizer, $invoiceId);

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        return $this->success($invoice);
    }

    /**
     * Get billing information
     */
    public function billingInfo(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        return $this->success([
            'company_name' => $organizer->company_name ?? $organizer->name,
            'cui' => $organizer->company_tax_id,
            'reg_number' => $organizer->company_registration,
            'address' => $this->formatAddress($organizer),
            'email' => $organizer->billing_email ?? $organizer->email,
            'phone' => $organizer->phone,
            'vat_payer' => $organizer->company_vat_payer ?? false,
        ]);
    }

    /**
     * Get payment methods
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Get saved payment methods from organizer
        // In a real implementation, this would be from a payment_methods table
        $methods = [];

        // Check if organizer has payment method data stored
        if (!empty($organizer->payment_methods)) {
            $methods = collect($organizer->payment_methods)->map(function ($method, $index) {
                return [
                    'id' => $index + 1,
                    'brand' => $method['brand'] ?? 'card',
                    'last4' => $method['last4'] ?? '****',
                    'exp_month' => $method['exp_month'] ?? '**',
                    'exp_year' => $method['exp_year'] ?? '**',
                    'is_default' => $method['is_default'] ?? ($index === 0),
                ];
            })->toArray();
        }

        // Calculate next invoice if there's pending commission
        $nextInvoice = null;
        $pendingCommission = $this->getPendingCommission($organizer);
        if ($pendingCommission > 0) {
            $nextInvoice = [
                'amount' => $pendingCommission,
                'due_date' => Carbon::now()->endOfMonth()->toDateString(),
            ];
        }

        return $this->success([
            'methods' => $methods,
            'next_invoice' => $nextInvoice,
        ]);
    }

    /**
     * Get organizer invoices — reads from DB if available, falls back to on-the-fly generation.
     */
    protected function getOrganizerInvoices(MarketplaceOrganizer $organizer, ?string $status, int $perPage, int $page): array
    {
        // Try DB-persisted invoices first.
        //
        // Only invoices ADDRESSED TO THE ORGANIZER belong in the organizer's
        // "Istoric facturi". On added_on_top commission the invoice recipient is
        // Ambilet's general client (meta.recipient_type = 'general_client') — the
        // organizer neither owes nor receives those, so they must not surface in
        // this history. Keep 'organizer' AND legacy/manual rows that have no
        // recipient_type key (they default to the organizer). `IS DISTINCT FROM`
        // is NULL-safe on Postgres, so the keyless rows are retained.
        $dbQuery = Invoice::where('marketplace_organizer_id', $organizer->id)
            ->whereRaw("(meta->>'recipient_type') IS DISTINCT FROM 'general_client'")
            ->orderByDesc('issue_date');

        if ($status && $status !== 'all') {
            $mappedStatus = $status === 'pending' ? 'outstanding' : $status;
            $dbQuery->where('status', $mappedStatus);
        }

        $dbCount = $dbQuery->count();

        // Only real DB-persisted invoices are shown to the organizer. The
        // former "synthesize INV-{organizer}-{yearmonth} from order history"
        // fallback (removed 2026-08-07) was a stopgap from when invoices
        // weren't persisted yet — it kept generating ghost rows on every
        // request, and after the 2026 bulk delete of legacy INV- invoices it
        // was resurrecting them here so they still appeared in the ambilet
        // organizer portal even though core.tixello.com no longer listed
        // them. Return empty when there's nothing real to show.
        if ($dbCount === 0) {
            return ['data' => [], 'total' => 0];
        }

        $dbInvoices = $dbQuery
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'date' => $inv->issue_date?->format('Y-m-d'),
                'description' => $inv->description,
                'amount' => (float) $inv->amount,
                'status' => $inv->status === 'outstanding' ? 'pending' : $inv->status,
            ])
            ->values()
            ->toArray();

        return ['data' => $dbInvoices, 'total' => $dbCount];
    }

    /**
     * Get invoice statistics
     */
    protected function getInvoiceStats(MarketplaceOrganizer $organizer): array
    {
        $totalOrders = $organizer->orders()->where('status', 'completed')->sum('total');
        $commissionRate = $organizer->getEffectiveCommissionRate();
        $totalCommission = round($totalOrders * $commissionRate / 100, 2);

        // Get paid commission (from payouts)
        $paidOut = (float) $organizer->total_paid_out;
        $pendingCommission = max(0, $totalCommission - $paidOut);

        return [
            'total_paid' => $paidOut,
            'total_invoices' => $organizer->orders()
                ->where('status', 'completed')
                ->selectRaw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(created_at, 'YYYY-MM') as month" : 'DATE_FORMAT(created_at, "%Y-%m") as month')
                ->distinct()
                ->count(),
            'pending_amount' => $pendingCommission,
            'avg_commission' => $commissionRate,
        ];
    }

    /**
     * Get single invoice detail — reads from DB if available, falls back to on-the-fly generation.
     */
    protected function getInvoiceDetail(MarketplaceOrganizer $organizer, int $invoiceId): ?array
    {
        $marketplace = $organizer->marketplaceClient;

        // Try DB first. Same recipient guard as the list: an organizer must not
        // be able to open a general_client invoice by guessing its id.
        $dbInvoice = Invoice::where('marketplace_organizer_id', $organizer->id)
            ->whereRaw("(meta->>'recipient_type') IS DISTINCT FROM 'general_client'")
            ->where('id', $invoiceId)
            ->first();

        if ($dbInvoice) {
            $meta = $dbInvoice->meta ?? [];
            return [
                'id' => $dbInvoice->id,
                'number' => $dbInvoice->number,
                'date' => $dbInvoice->issue_date?->format('Y-m-d'),
                'due_date' => $dbInvoice->due_date?->format('Y-m-d'),
                'status' => $dbInvoice->status === 'outstanding' ? 'pending' : $dbInvoice->status,
                'issuer' => $meta['issuer'] ?? $this->buildIssuerData($marketplace),
                'client' => $meta['client'] ?? [
                    'name' => $organizer->company_name ?? $organizer->name,
                    'address' => $this->formatAddress($organizer),
                    'cui' => $organizer->company_tax_id ?? '',
                ],
                'items' => $meta['items'] ?? [],
                'subtotal' => (float) $dbInvoice->subtotal,
                'vat_rate' => (float) $dbInvoice->vat_rate,
                'vat' => (float) $dbInvoice->vat_amount,
                'total' => (float) $dbInvoice->amount,
            ];
        }

        // Synthetic-invoice fallback removed 2026-08-07 — same rationale as
        // getOrganizerInvoices(): once real invoices are the source of truth,
        // fabricating a detail view from order history would keep the
        // deleted INV- ghosts openable via URL guessing. Return null so the
        // caller yields a clean 404.
        return null;
    }

    /**
     * Build issuer data from marketplace business details.
     */
    protected function buildIssuerData($marketplace): array
    {
        return [
            'name' => $marketplace->company_name ?? $marketplace->name,
            'cui' => $marketplace->cui ?? '',
            'reg_com' => $marketplace->reg_com ?? '',
            'vat_payer' => (bool) $marketplace->vat_payer,
            'bank_name' => $marketplace->bank_name ?? '',
            'iban' => $marketplace->bank_account ?? '',
            'address' => implode(', ', array_filter([
                $marketplace->address,
                $marketplace->city,
                $marketplace->state,
            ])),
            'email' => $marketplace->contact_email ?? '',
            'phone' => $marketplace->contact_phone ?? '',
            'website' => $marketplace->website ?? '',
        ];
    }

    /**
     * Get pending commission for next invoice
     */
    protected function getPendingCommission(MarketplaceOrganizer $organizer): float
    {
        $currentMonth = Carbon::now()->startOfMonth();

        $monthOrders = $organizer->orders()
            ->where('status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->sum('total');

        $commissionRate = $organizer->getEffectiveCommissionRate();
        return round($monthOrders * $commissionRate / 100, 2);
    }

    /**
     * Format organizer address
     */
    protected function formatAddress(MarketplaceOrganizer $organizer): string
    {
        $parts = array_filter([
            $organizer->company_address,
            $organizer->company_city,
            $organizer->company_county,
        ]);

        return implode(', ', $parts) ?: '-';
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}

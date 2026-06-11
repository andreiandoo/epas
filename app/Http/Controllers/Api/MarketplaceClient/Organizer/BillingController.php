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
        // Try DB-persisted invoices first
        $dbQuery = Invoice::where('marketplace_organizer_id', $organizer->id)
            ->orderByDesc('issue_date');

        if ($status && $status !== 'all') {
            $mappedStatus = $status === 'pending' ? 'outstanding' : $status;
            $dbQuery->where('status', $mappedStatus);
        }

        $dbCount = $dbQuery->count();

        if ($dbCount > 0) {
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

        // Fallback: generate from order history
        $allOrders = $organizer->orders()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        $invoices = [];
        $grouped = $allOrders->groupBy(function ($order) {
            return $order->paid_at ? $order->paid_at->format('Y-m') : $order->created_at->format('Y-m');
        });

        $invoiceId = 1;
        foreach ($grouped as $month => $orders) {
            $totalAmount = $orders->sum('total');
            $commissionRate = $organizer->getEffectiveCommissionRate();
            $commissionAmount = round($totalAmount * $commissionRate / 100, 2);

            $monthDate = Carbon::parse($month . '-01');
            $isPaid = $monthDate->endOfMonth()->lt(Carbon::now()->subMonth());
            $invoiceStatus = $isPaid ? 'paid' : 'pending';

            if ($status && $status !== 'all' && $invoiceStatus !== $status) {
                continue;
            }

            $invoices[] = [
                'id' => $invoiceId,
                'number' => 'INV-' . $organizer->id . '-' . $monthDate->format('Ym'),
                'date' => $monthDate->format('Y-m-d'),
                'description' => 'Comision vânzări ' . $monthDate->format('F Y'),
                'amount' => $commissionAmount,
                'status' => $invoiceStatus,
            ];
            $invoiceId++;
        }

        $total = count($invoices);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($invoices, $offset, $perPage),
            'total' => $total,
        ];
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

        // Try DB first
        $dbInvoice = Invoice::where('marketplace_organizer_id', $organizer->id)
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

        // Fallback: generate from order history
        $invoices = $this->getOrganizerInvoices($organizer, null, 100, 1);

        foreach ($invoices['data'] as $invoice) {
            if ($invoice['id'] === $invoiceId) {
                $monthStr = substr($invoice['number'], -6);
                $year = substr($monthStr, 0, 4);
                $month = substr($monthStr, 4, 2);
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                $orders = $organizer->orders()
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $totalAmount = $orders->sum('total');
                $commissionRate = $organizer->getEffectiveCommissionRate();
                $commissionAmount = round($totalAmount * $commissionRate / 100, 2);
                $vatRate = $marketplace->vat_payer ? 19 : 0;
                $vatAmount = $vatRate > 0 ? round($commissionAmount * $vatRate / 100, 2) : 0;

                return [
                    'id' => $invoice['id'],
                    'number' => $invoice['number'],
                    'date' => $invoice['date'],
                    'due_date' => Carbon::parse($invoice['date'])->endOfMonth()->format('Y-m-d'),
                    'status' => $invoice['status'],
                    'issuer' => $this->buildIssuerData($marketplace),
                    'client' => [
                        'name' => $organizer->company_name ?? $organizer->name,
                        'address' => $this->formatAddress($organizer),
                        'cui' => $organizer->company_tax_id ?? '',
                    ],
                    'items' => [
                        [
                            'description' => "Comision vânzări bilete ({$commissionRate}%)",
                            'quantity' => $orders->count(),
                            'price' => $orders->count() > 0 ? round($commissionAmount / $orders->count(), 2) : 0,
                            'total' => $commissionAmount,
                        ],
                    ],
                    'subtotal' => $commissionAmount,
                    'vat_rate' => $vatRate,
                    'vat' => $vatAmount,
                    'total' => $commissionAmount + $vatAmount,
                ];
            }
        }

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

<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use Carbon\Carbon;

class InvoiceGeneratorService
{
    /**
     * Generate invoices for a single organizer based on their order history.
     * Returns the number of newly created invoices.
     */
    public function generateForOrganizer(MarketplaceOrganizer $organizer, MarketplaceClient $marketplace): int
    {
        $orders = $organizer->orders()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        if ($orders->isEmpty()) {
            return 0;
        }

        $grouped = $orders->groupBy(function ($order) {
            return $order->paid_at
                ? $order->paid_at->format('Y-m')
                : $order->created_at->format('Y-m');
        });

        $created = 0;
        $commissionRate = $organizer->getEffectiveCommissionRate();
        $vatRate = $marketplace->vat_payer ? 19 : 0;
        $currency = $marketplace->currency ?? 'RON';

        foreach ($grouped as $month => $monthOrders) {
            $monthDate = Carbon::parse($month . '-01');
            $totalAmount = $monthOrders->sum('total');
            $commissionAmount = round($totalAmount * $commissionRate / 100, 2);
            $vatAmount = $vatRate > 0 ? round($commissionAmount * $vatRate / 100, 2) : 0;

            // Determine status
            $isPaid = $monthDate->copy()->endOfMonth()->lt(Carbon::now()->subMonth());

            // Build invoice number
            $number = $this->generateInvoiceNumber($organizer, $monthDate, $marketplace);

            // Check if already exists
            $exists = Invoice::where('marketplace_client_id', $marketplace->id)
                ->where('marketplace_organizer_id', $organizer->id)
                ->where('number', $number)
                ->exists();

            if ($exists) {
                continue;
            }

            // Build issuer data
            $issuer = [
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

            // Build client data
            $client = [
                'name' => $organizer->company_name ?? $organizer->name,
                'cui' => $organizer->company_tax_id ?? '',
                'reg_com' => $organizer->company_registration ?? '',
                'address' => implode(', ', array_filter([
                    $organizer->company_address,
                    $organizer->company_city,
                    $organizer->company_county,
                ])),
            ];

            // Build line items
            $items = [
                [
                    'description' => "Comision vânzări bilete ({$commissionRate}%)",
                    'quantity' => $monthOrders->count(),
                    'price' => $monthOrders->count() > 0 ? round($commissionAmount / $monthOrders->count(), 2) : 0,
                    'total' => $commissionAmount,
                ],
            ];

            Invoice::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer->id,
                'number' => $number,
                'type' => 'fiscal',
                'description' => 'Comision vânzări ' . $monthDate->translatedFormat('F Y'),
                'issue_date' => $monthDate->copy()->startOfMonth(),
                'period_start' => $monthDate->copy()->startOfMonth(),
                'period_end' => $monthDate->copy()->endOfMonth(),
                'due_date' => $monthDate->copy()->endOfMonth(),
                'subtotal' => $commissionAmount,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'amount' => $commissionAmount + $vatAmount,
                'currency' => $currency,
                'status' => $isPaid ? 'paid' : 'outstanding',
                'paid_at' => $isPaid ? $monthDate->copy()->endOfMonth() : null,
                'meta' => [
                    'issuer' => $issuer,
                    'client' => $client,
                    'items' => $items,
                    'order_count' => $monthOrders->count(),
                    'total_sales' => $totalAmount,
                    'commission_rate' => $commissionRate,
                ],
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Generate invoices for all organizers that have completed orders.
     */
    public function generateForAllOrganizers(MarketplaceClient $marketplace): int
    {
        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplace->id)
            ->whereHas('orders', fn ($q) => $q->where('status', 'completed'))
            ->get();

        $total = 0;

        foreach ($organizers as $organizer) {
            $total += $this->generateForOrganizer($organizer, $marketplace);
        }

        return $total;
    }

    /**
     * Generate invoice number using marketplace settings or fallback format.
     */
    protected function generateInvoiceNumber(MarketplaceOrganizer $organizer, Carbon $monthDate, MarketplaceClient $marketplace): string
    {
        $settings = $marketplace->settings ?? [];
        $prefix = $settings['invoice_prefix'] ?? null;

        if ($prefix) {
            return $prefix . '-' . $organizer->id . '-' . $monthDate->format('Ym');
        }

        return 'INV-' . $organizer->id . '-' . $monthDate->format('Ym');
    }
}

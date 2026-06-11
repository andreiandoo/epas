<?php

namespace App\Services\Cashless;

use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessGdprRequest;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CustomerProfile;
use App\Models\Customer;
use App\Models\WristbandTransaction;
use Illuminate\Support\Facades\Storage;

class GdprService
{
    /**
     * Create an export of all customer data (right of access / portability).
     */
    public function exportCustomerData(int $customerId, int $tenantId): array
    {
        $customer = Customer::findOrFail($customerId);

        $accounts = CashlessAccount::where('customer_id', $customerId)->get();
        $transactions = WristbandTransaction::where('customer_id', $customerId)->get();
        $sales = CashlessSale::where('customer_id', $customerId)
            ->with('items')
            ->get();
        $profiles = CustomerProfile::where('customer_id', $customerId)->get();

        $data = [
            'customer' => $customer->only([
                'id', 'first_name', 'last_name', 'email', 'phone',
                'date_of_birth', 'gender', 'created_at',
            ]),
            'accounts' => $accounts->map(fn ($a) => $a->only([
                'account_number', 'balance_cents', 'total_topped_up_cents',
                'total_spent_cents', 'total_cashed_out_cents', 'currency',
                'status', 'activated_at', 'closed_at',
            ])),
            'transactions' => $transactions->map(fn ($t) => $t->only([
                'transaction_type', 'amount_cents', 'balance_before_cents',
                'balance_after_cents', 'currency', 'description', 'created_at',
            ])),
            'purchases' => $sales->map(fn ($s) => [
                'sale_number'  => $s->sale_number,
                'total_cents'  => $s->total_cents,
                'tip_cents'    => $s->tip_cents,
                'status'       => $s->status->value,
                'sold_at'      => $s->sold_at?->toIso8601String(),
                'items'        => $s->items->map(fn ($i) => $i->only([
                    'product_name', 'quantity', 'unit_price_cents', 'total_cents',
                ])),
            ]),
            'profiles' => $profiles->map(fn ($p) => $p->only([
                'segment', 'tags', 'spending_score', 'frequency_score',
                'diversity_score', 'overall_score', 'calculated_at',
            ])),
            'exported_at' => now()->toIso8601String(),
        ];

        // Save to file
        $filename = "gdpr-export/customer-{$customerId}-" . now()->format('Y-m-d-His') . '.json';
        Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'file_path' => $filename,
            'data'      => $data,
        ];
    }

    /**
     * Request data deletion (creates GDPR request for admin review).
     */
    public function requestDeletion(int $customerId, int $tenantId): CashlessGdprRequest
    {
        return CashlessGdprRequest::create([
            'tenant_id'    => $tenantId,
            'customer_id'  => $customerId,
            'request_type' => 'deletion',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);
    }

    /**
     * Process a deletion request (anonymize customer data).
     */
    public function processDeletion(CashlessGdprRequest $request, int $processedBy): void
    {
        $customerId = $request->customer_id;

        // Anonymize transactions (keep financial data for fiscal requirements)
        WristbandTransaction::where('customer_id', $customerId)
            ->update([
                'customer_email' => null,
                'customer_name'  => null,
                'operator'       => 'gdpr_anonymized',
            ]);

        // Delete customer profiles
        CustomerProfile::where('customer_id', $customerId)->delete();

        // Anonymize accounts (keep balance for reconciliation)
        CashlessAccount::where('customer_id', $customerId)
            ->update(['meta' => null]);

        // Update request
        $request->update([
            'status'       => 'completed',
            'processed_at' => now(),
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Anonymize old data (run periodically).
     */
    public function anonymizeOldData(int $monthsOld = 24): array
    {
        $cutoff = now()->subMonths($monthsOld);
        $anonymized = 0;

        // Anonymize transactions older than cutoff
        $anonymized += WristbandTransaction::where('created_at', '<', $cutoff)
            ->whereNotNull('customer_email')
            ->update([
                'customer_email' => null,
                'customer_name'  => null,
            ]);

        // Delete profiles older than 1 year
        $profileCutoff = now()->subYear();
        $profilesDeleted = CustomerProfile::where('calculated_at', '<', $profileCutoff)->delete();

        return [
            'transactions_anonymized' => $anonymized,
            'profiles_deleted'        => $profilesDeleted,
        ];
    }
}

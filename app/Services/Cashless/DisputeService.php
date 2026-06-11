<?php

namespace App\Services\Cashless;

use App\Models\Cashless\CashlessDispute;
use App\Models\Cashless\CashlessSale;
use App\Models\WristbandTransaction;
use Illuminate\Support\Facades\DB;

class DisputeService
{
    public function __construct(
        private CashlessAccountService $accountService,
    ) {}

    public function openDispute(
        int $accountId,
        string $disputeType,
        string $description,
        ?int $transactionId = null,
        ?int $saleId = null,
        ?array $evidence = null,
    ): CashlessDispute {
        $account = \App\Models\Cashless\CashlessAccount::findOrFail($accountId);

        $amountCents = 0;
        $vendorId = null;

        if ($transactionId) {
            $tx = WristbandTransaction::find($transactionId);
            $amountCents = $tx?->amount_cents ?? 0;
            $vendorId = $tx?->vendor_id;
        } elseif ($saleId) {
            $sale = CashlessSale::find($saleId);
            $amountCents = $sale?->total_cents ?? 0;
            $vendorId = $sale?->vendor_id;
        }

        $priority = match (true) {
            $amountCents >= 50000 => 'urgent',
            $amountCents >= 20000 => 'high',
            $amountCents >= 5000 => 'medium',
            default => 'low',
        };

        return CashlessDispute::create([
            'tenant_id'                => $account->tenant_id,
            'festival_edition_id'      => $account->festival_edition_id,
            'cashless_account_id'      => $account->id,
            'customer_id'              => $account->customer_id,
            'wristband_transaction_id' => $transactionId,
            'cashless_sale_id'         => $saleId,
            'vendor_id'                => $vendorId,
            'dispute_type'             => $disputeType,
            'status'                   => 'open',
            'amount_disputed_cents'    => $amountCents,
            'description'              => $description,
            'evidence'                 => $evidence,
            'priority'                 => $priority,
            'opened_at'                => now(),
        ]);
    }

    public function assign(CashlessDispute $dispute, int $adminUserId): void
    {
        $dispute->update([
            'assigned_to' => $adminUserId,
            'status'      => 'investigating',
        ]);
    }

    public function resolveWithRefund(CashlessDispute $dispute, int $refundCents, ?string $reason = null): CashlessDispute
    {
        return DB::transaction(function () use ($dispute, $refundCents, $reason) {
            $this->accountService->refund(
                $dispute->account,
                $refundCents,
                "Dispute #{$dispute->id} resolved: {$reason}",
            );

            $status = $refundCents >= $dispute->amount_disputed_cents
                ? 'resolved_refund'
                : 'resolved_partial_refund';

            $dispute->update([
                'status'               => $status,
                'amount_refunded_cents' => $refundCents,
                'resolved_at'          => now(),
                'resolution_reason'    => $reason,
            ]);

            return $dispute->fresh();
        });
    }

    public function resolveNoAction(CashlessDispute $dispute, string $reason): CashlessDispute
    {
        $dispute->update([
            'status'            => 'resolved_no_action',
            'resolved_at'       => now(),
            'resolution_reason' => $reason,
        ]);

        return $dispute->fresh();
    }

    public function reject(CashlessDispute $dispute, string $reason): CashlessDispute
    {
        $dispute->update([
            'status'            => 'rejected',
            'resolved_at'       => now(),
            'resolution_reason' => $reason,
        ]);

        return $dispute->fresh();
    }

    public function escalate(CashlessDispute $dispute): void
    {
        $dispute->update([
            'status'   => 'escalated',
            'priority' => 'urgent',
        ]);
    }
}

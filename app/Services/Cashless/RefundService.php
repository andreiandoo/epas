<?php

namespace App\Services\Cashless;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessRefund;
use App\Models\Cashless\CashlessSale;
use App\Models\VendorSaleItem;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        private CashlessAccountService $accountService,
    ) {}

    /**
     * Request a refund (creates pending refund).
     */
    public function requestRefund(
        CashlessSale $sale,
        string $refundType,
        string $reason,
        ?array $itemIds = null,
        ?int $requestedByEmployeeId = null,
    ): CashlessRefund {
        if (! in_array($sale->status, [SaleStatus::Completed, SaleStatus::PartialRefund])) {
            throw new \InvalidArgumentException("Cannot refund a sale with status '{$sale->status->value}'.");
        }

        // Calculate refund amount
        if ($refundType === 'full') {
            $totalRefundCents = $sale->total_cents + $sale->tip_cents;
            $items = null;
        } else {
            if (empty($itemIds)) {
                throw new \InvalidArgumentException('Partial refund requires item IDs.');
            }

            $saleItems = VendorSaleItem::whereIn('id', $itemIds)
                ->where('cashless_sale_id', $sale->id)
                ->get();

            if ($saleItems->isEmpty()) {
                throw new \InvalidArgumentException('No valid items found for refund.');
            }

            $totalRefundCents = $saleItems->sum('total_cents') + $saleItems->sum('sgr_cents');
            $items = $saleItems->map(fn ($i) => [
                'vendor_sale_item_id' => $i->id,
                'quantity'            => $i->quantity,
                'amount_cents'        => $i->total_cents + $i->sgr_cents,
            ])->toArray();
        }

        return CashlessRefund::create([
            'tenant_id'                  => $sale->tenant_id,
            'festival_edition_id'        => $sale->festival_edition_id,
            'cashless_sale_id'           => $sale->id,
            'cashless_account_id'        => $sale->cashless_account_id,
            'customer_id'                => $sale->customer_id,
            'vendor_id'                  => $sale->vendor_id,
            'refund_type'                => $refundType,
            'status'                     => 'pending',
            'requested_by_employee_id'   => $requestedByEmployeeId,
            'requested_at'               => now(),
            'total_refund_cents'         => $totalRefundCents,
            'currency'                   => $sale->currency,
            'reason'                     => $reason,
            'items'                      => $items,
        ]);
    }

    /**
     * Approve and process a refund.
     */
    public function approveAndProcess(
        CashlessRefund $refund,
        int $approvedByEmployeeId,
    ): CashlessRefund {
        if (! $refund->isPending()) {
            throw new \InvalidArgumentException("Refund is not pending (status: {$refund->status}).");
        }

        return DB::transaction(function () use ($refund, $approvedByEmployeeId) {
            // Credit the account
            $transaction = $this->accountService->refund(
                $refund->account,
                $refund->total_refund_cents,
                "Refund for sale {$refund->sale->sale_number}: {$refund->reason}",
            );

            // Update sale status
            $sale = $refund->sale;
            $newStatus = $refund->refund_type === 'full'
                ? SaleStatus::Refunded
                : SaleStatus::PartialRefund;
            $sale->update(['status' => $newStatus]);

            // Update refund record
            $refund->update([
                'status'                   => 'processed',
                'approved_by_employee_id'  => $approvedByEmployeeId,
                'approved_at'              => now(),
                'processed_at'             => now(),
                'wristband_transaction_id' => $transaction->id,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Reject a refund request.
     */
    public function reject(
        CashlessRefund $refund,
        string $rejectionReason,
        int $rejectedByEmployeeId,
    ): CashlessRefund {
        if (! $refund->isPending()) {
            throw new \InvalidArgumentException("Refund is not pending (status: {$refund->status}).");
        }

        $refund->update([
            'status'                  => 'rejected',
            'approved_by_employee_id' => $rejectedByEmployeeId,
            'rejected_at'             => now(),
            'rejection_reason'        => $rejectionReason,
        ]);

        return $refund->fresh();
    }

    /**
     * Manager direct refund (bypass approval).
     */
    public function directRefund(
        CashlessSale $sale,
        string $refundType,
        string $reason,
        int $managerEmployeeId,
        ?array $itemIds = null,
    ): CashlessRefund {
        $refund = $this->requestRefund($sale, $refundType, $reason, $itemIds, $managerEmployeeId);

        return $this->approveAndProcess($refund, $managerEmployeeId);
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderCompanyInvoice;
use App\Services\Invoicing\CompanyInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Factura pe firmă a unei comenzi plătite.
 * - fără $rowId: creează cererile (dacă firma n-a putut fi verificată la checkout)
 * - cu $rowId: emite factura marketplace-ului (Oblio, SPV, email); reia pașii rămași
 */
class ProcessOrderCompanyInvoiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 6;
    public int $timeout = 90;

    public function __construct(public int $orderId, public ?int $rowId = null)
    {
    }

    /** 1 min, 5 min, 15 min, 1 h, 3 h */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(CompanyInvoiceService $service): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        if ($this->rowId === null) {
            $service->processPaidOrder($order);

            return;
        }

        $row = OrderCompanyInvoice::find($this->rowId);
        if (!$row || !$row->isMarketplace()) {
            return;
        }
        if ($row->status === OrderCompanyInvoice::STATUS_ISSUED && $row->sent_to_customer_at) {
            return;
        }

        try {
            $service->issueMarketplaceInvoice($row);
        } catch (\Throwable $e) {
            $row->update([
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                // emisă în Oblio dar netrimisă rămâne „issued”
                'status' => $row->fresh()->status === OrderCompanyInvoice::STATUS_ISSUED
                    ? OrderCompanyInvoice::STATUS_ISSUED
                    : OrderCompanyInvoice::STATUS_FAILED,
            ]);
            Log::warning('[CompanyInvoice] marketplace invoice step failed', [
                'order_id' => $order->id,
                'row_id' => $row->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

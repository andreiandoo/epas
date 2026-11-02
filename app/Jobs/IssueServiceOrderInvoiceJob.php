<?php

namespace App\Jobs;

use App\Models\ServiceOrder;
use App\Services\Invoicing\ServiceOrderInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Factura unei comenzi de servicii plătite; reia pașii rămași. */
class IssueServiceOrderInvoiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 6;
    public int $timeout = 90;

    public function __construct(public int $serviceOrderId)
    {
    }

    /** 1 min, 5 min, 15 min, 1 h, 3 h */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(ServiceOrderInvoiceService $service): void
    {
        $order = ServiceOrder::find($this->serviceOrderId);
        if ($order) {
            $service->process($order);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $order = ServiceOrder::find($this->serviceOrderId);
        if ($order) {
            app(ServiceOrderInvoiceService::class)->alertAdmin($order, $e?->getMessage() ?? 'Eroare necunoscută.');
        }
    }
}

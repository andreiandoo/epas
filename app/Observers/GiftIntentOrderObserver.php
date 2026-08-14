<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Tixello\GiftPurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transformă cadourile în transferuri, când comanda a fost plătită.
 *
 * Aceeași formă ca observatorul de Facebook CAPI, și din același motiv:
 * `DB::afterCommit` — dacă tranzacția de checkout se întoarce, nu vrem
 * transferuri emise pentru o comandă care n-a existat niciodată.
 *
 * Nu se face pe coadă: conversia e o scriere scurtă în două tabele, iar dacă
 * ar întârzia, prietenul ar primi notificarea înaintea biletului.
 */
class GiftIntentOrderObserver
{
    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];

    public function created(Order $order): void
    {
        if (! in_array($order->status, self::PAID_STATUSES, true)) {
            return;
        }

        $this->convertAfterCommit($order);
    }

    public function updated(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        if (! in_array($order->status, self::PAID_STATUSES, true)) {
            return;
        }

        /* Deja era plătită: un webhook repetat n-are ce converti a doua oară.
           (Serviciul e oricum idempotent — asta e doar prima poartă.) */
        if (in_array($order->getOriginal('status'), self::PAID_STATUSES, true)) {
            return;
        }

        $this->convertAfterCommit($order);
    }

    private function convertAfterCommit(Order $order): void
    {
        $orderId = $order->id;

        DB::afterCommit(function () use ($orderId) {
            try {
                $fresh = Order::find($orderId);

                if ($fresh) {
                    app(GiftPurchaseService::class)->convert($fresh);
                }
            } catch (\Throwable $e) {
                Log::warning('Conversia cadourilor a esuat', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}

<?php

namespace App\Services\Onboarding;

use App\Models\Customer;
use App\Models\Event;
use App\Models\ExchangeRate;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cifrele reale ale platformei pentru prima pagină a înscrierii. Includ și datele
 * istorice importate din vechiul Ambilet (orders.source = legacy_import), care stau
 * în aceleași tabele. Cache de 30 de secunde; pagina le cere din nou singură.
 * O cifră care nu se poate calcula lipsește.
 */
class PlatformStats
{
    private const PAID = ['paid', 'confirmed', 'completed'];

    public function get(): array
    {
        return Cache::remember('signup:platform-stats:v4', 30, function () {
            return array_filter([
                'sales' => $this->count(fn () => $this->salesEur()),
                'tickets' => $this->count(fn () => Ticket::count()),
                'events' => $this->count(fn () => Event::count()),
                'buyers' => $this->count(fn () => Customer::count() + MarketplaceCustomer::count()),
            ], fn ($v) => $v !== null && $v > 0);
        });
    }

    /** Valoarea comenzilor plătite, în euro: lei convertiți la ultimul curs EUR/RON. */
    private function salesEur(): int
    {
        $ron = (float) Order::whereIn('status', self::PAID)
            ->where(fn ($q) => $q->where('currency', 'RON')->orWhereNull('currency'))
            ->sum('total');
        $eur = (float) Order::whereIn('status', self::PAID)->where('currency', 'EUR')->sum('total');
        $rate = (float) (ExchangeRate::getLatestRate('EUR', 'RON') ?: 5);

        return (int) round($eur + $ron / $rate);
    }

    private function count(callable $query): ?int
    {
        try {
            return (int) $query();
        } catch (\Throwable $e) {
            Log::warning('Signup: o cifră de platformă nu s-a putut calcula', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

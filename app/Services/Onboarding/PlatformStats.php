<?php

namespace App\Services\Onboarding;

use App\Models\Customer;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cifrele reale ale platformei pentru prima pagină a înscrierii, cu aceleași
 * definiții ca în dashboard-ul din admin (StatsOverview). Cache de 30 de secunde;
 * pagina le cere din nou singură. O cifră care nu se poate calcula lipsește.
 */
class PlatformStats
{
    private const PAID = ['paid', 'confirmed', 'completed'];

    public function get(): array
    {
        return Cache::remember('signup:platform-stats:v2', 30, function () {
            return array_filter([
                // „Tickets issued” din admin: bilete valide din comenzi plătite
                'tickets' => $this->count(fn () => Ticket::where('status', 'valid')->whereHas('order', fn ($q) => $q->whereIn('status', self::PAID))->count()),
                'orders' => $this->count(fn () => Order::whereIn('status', self::PAID)->count()),
                'events' => $this->count(fn () => Event::count()),
                'buyers' => $this->count(fn () => Customer::count() + MarketplaceCustomer::count()),
                'accounts' => $this->count(fn () => Tenant::where('status', 'active')->where('is_demo_shadow', false)->count() + MarketplaceClient::where('status', 'active')->count()),
            ], fn ($v) => $v !== null && $v > 0);
        });
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

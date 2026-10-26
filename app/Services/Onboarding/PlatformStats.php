<?php

namespace App\Services\Onboarding;

use App\Models\Customer;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cifrele reale ale platformei pentru prima pagină a înscrierii. Se calculează
 * din baza de date, rotunjite în jos, și stau 6 ore în cache. Dacă o interogare
 * eșuează, cifra respectivă lipsește din pagină în loc să apară inventată.
 */
class PlatformStats
{
    public function get(): array
    {
        return Cache::remember('signup:platform-stats', now()->addHours(6), function () {
            return array_filter([
                'tickets' => $this->count(fn () => Ticket::whereNotIn('status', ['cancelled', 'refunded', 'void'])->count()),
                'events' => $this->count(fn () => Event::count()),
                'organizers' => $this->count(fn () => Tenant::where('is_demo_shadow', false)->where('status', 'active')->count() + MarketplaceOrganizer::count()),
                'buyers' => $this->count(fn () => Customer::count()),
            ], fn ($v) => $v !== null && $v > 0);
        });
    }

    /** „peste 12.000”: rotunjit în jos la două cifre semnificative, ca să nu promitem mai mult. */
    public static function floor(int $value): int
    {
        if ($value < 100) {
            return $value;
        }
        $step = 10 ** (strlen((string) $value) - 2);

        return intdiv($value, $step) * $step;
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

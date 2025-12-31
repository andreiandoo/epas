<?php

namespace App\Jobs\Tracking;

use App\Models\FeatureStore\FsPersonTicketPref;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calculates ticket preferences for persons based on their purchase history.
 *
 * Analyzes:
 * - Preferred ticket categories (GA, VIP, etc.)
 * - Price sensitivity / preferred price bands
 * - Average purchase price
 */
class CalculateTicketPreferencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    protected ?int $tenantId;
    protected ?int $personId;
    protected int $lookbackDays;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $tenantId = null, ?int $personId = null, int $lookbackDays = 365)
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
        $this->lookbackDays = $lookbackDays;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('CalculateTicketPreferences started', [
            'tenant_id' => $this->tenantId,
            'person_id' => $this->personId,
            'lookback_days' => $this->lookbackDays,
        ]);

        $startTime = microtime(true);
        $processed = 0;
        $errors = 0;

        try {
            // Get persons with order_completed events
            $persons = $this->getPersonsToProcess();

            foreach ($persons as $person) {
                try {
                    $this->calculatePreferencesForPerson($person->tenant_id, $person->person_id);
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to calculate ticket preferences for person', [
                        'tenant_id' => $person->tenant_id,
                        'person_id' => $person->person_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('CalculateTicketPreferences completed', [
                'processed' => $processed,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('CalculateTicketPreferences job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Get persons who have made purchases.
     */
    protected function getPersonsToProcess()
    {
        $query = TxEvent::query()
            ->select('tenant_id', 'person_id')
            ->whereNotNull('person_id')
            ->where('event_name', 'order_completed')
            ->where('occurred_at', '>=', now()->subDays($this->lookbackDays))
            ->groupBy('tenant_id', 'person_id');

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        if ($this->personId) {
            $query->where('person_id', $this->personId);
        }

        return $query->get();
    }

    /**
     * Calculate ticket preferences for a specific person.
     */
    protected function calculatePreferencesForPerson(int $tenantId, int $personId): void
    {
        // Get all order_completed events with ticket details
        $orders = TxEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->where('event_name', 'order_completed')
            ->where('occurred_at', '>=', now()->subDays($this->lookbackDays))
            ->select('payload', 'occurred_at')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        // Aggregate by ticket category
        $categoryStats = [];
        $allPrices = [];

        foreach ($orders as $order) {
            $items = $order->payload['items'] ?? [];

            foreach ($items as $item) {
                $category = $this->normalizeCategory($item['ticket_category'] ?? $item['ticket_type_name'] ?? 'General');
                $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 1);

                if (!isset($categoryStats[$category])) {
                    $categoryStats[$category] = [
                        'purchases_count' => 0,
                        'total_price' => 0,
                        'prices' => [],
                    ];
                }

                $categoryStats[$category]['purchases_count'] += $quantity;
                $categoryStats[$category]['total_price'] += $price * $quantity;

                for ($i = 0; $i < $quantity; $i++) {
                    $categoryStats[$category]['prices'][] = $price;
                    $allPrices[] = $price;
                }
            }
        }

        if (empty($categoryStats)) {
            return;
        }

        // Calculate total purchases for preference scoring
        $totalPurchases = array_sum(array_column($categoryStats, 'purchases_count'));

        // Calculate and upsert preferences per category
        foreach ($categoryStats as $category => $stats) {
            $avgPrice = $stats['purchases_count'] > 0
                ? $stats['total_price'] / $stats['purchases_count']
                : 0;

            // Preference score = proportion of total purchases in this category
            $preferenceScore = $totalPurchases > 0
                ? ($stats['purchases_count'] / $totalPurchases) * 100
                : 0;

            // Determine price band based on average price
            $priceBand = FsPersonTicketPref::determinePriceBand($avgPrice);

            FsPersonTicketPref::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'person_id' => $personId,
                    'ticket_category' => $category,
                ],
                [
                    'purchases_count' => $stats['purchases_count'],
                    'avg_price' => round($avgPrice, 2),
                    'preference_score' => round($preferenceScore, 4),
                    'price_band' => $priceBand,
                ]
            );
        }

        // Also calculate overall price sensitivity metrics
        if (!empty($allPrices)) {
            $overallAvg = array_sum($allPrices) / count($allPrices);
            $overallBand = FsPersonTicketPref::determinePriceBand($overallAvg);

            // Store as a special "_overall" category
            FsPersonTicketPref::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'person_id' => $personId,
                    'ticket_category' => '_overall',
                ],
                [
                    'purchases_count' => count($allPrices),
                    'avg_price' => round($overallAvg, 2),
                    'preference_score' => 100,
                    'price_band' => $overallBand,
                ]
            );
        }
    }

    /**
     * Normalize ticket category names to standard categories.
     */
    protected function normalizeCategory(string $category): string
    {
        $category = trim($category);
        $lower = strtolower($category);

        // Map common variations to standard categories
        $mappings = [
            'general admission' => 'GA',
            'general' => 'GA',
            'ga' => 'GA',
            'standard' => 'GA',
            'regular' => 'GA',
            'vip' => 'VIP',
            'v.i.p.' => 'VIP',
            'premium' => 'Premium',
            'gold' => 'Premium',
            'platinum' => 'Premium',
            'early bird' => 'EarlyBird',
            'earlybird' => 'EarlyBird',
            'early' => 'EarlyBird',
            'student' => 'Student',
            'students' => 'Student',
            'group' => 'Group',
            'family' => 'Family',
            'family pack' => 'Family',
            'standing' => 'Standing',
            'standing room' => 'Standing',
            'seated' => 'Seated',
            'seat' => 'Seated',
        ];

        if (isset($mappings[$lower])) {
            return $mappings[$lower];
        }

        // Check for partial matches
        foreach ($mappings as $pattern => $normalized) {
            if (str_contains($lower, $pattern)) {
                return $normalized;
            }
        }

        // Return original if no match (capitalize first letter)
        return ucfirst($category);
    }

    /**
     * Dispatch job for a specific person.
     */
    public static function dispatchForPerson(int $tenantId, int $personId): void
    {
        static::dispatch($tenantId, $personId, 365);
    }

    /**
     * Dispatch job for a specific tenant.
     */
    public static function dispatchForTenant(int $tenantId): void
    {
        static::dispatch($tenantId, null, 365);
    }

    /**
     * Dispatch job for all tenants.
     */
    public static function dispatchForAll(): void
    {
        static::dispatch(null, null, 365);
    }
}

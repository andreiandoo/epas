<?php

namespace App\Services\Seating\Pricing;

use App\Services\Seating\Pricing\Contracts\DynamicPricingEngine;
use App\Services\Seating\Pricing\DTO\PriceDecision;
use App\Models\Seating\EventSeat;
use App\Models\Seating\DynamicPriceOverride;
use App\Models\Seating\DynamicPricingRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * DefaultPricingEngine
 *
 * Default implementation of dynamic pricing with override support
 * Actual pricing strategies are stubs - extend for production algorithms
 */
class DefaultPricingEngine implements DynamicPricingEngine
{
    private int $cacheTtl;

    public function __construct()
    {
        $this->cacheTtl = config('seating.dynamic_pricing.cache_ttl', 60);
    }

    /**
     * Compute effective price for a specific seat
     */
    public function computeEffectivePrice(int $eventSeatingId, string $seatUid): PriceDecision
    {
        // Check cache first
        $cacheKey = "price:{$eventSeatingId}:{$seatUid}";
        $cached = Cache::get($cacheKey);

        // SECURITY FIX: Use JSON instead of unserialize to prevent PHP Object Injection
        if ($cached) {
            $data = json_decode($cached, true);
            if ($data && isset($data['base_price_cents'])) {
                return new PriceDecision(
                    basePriceCents: $data['base_price_cents'],
                    effectivePriceCents: $data['effective_price_cents'],
                    sourceRuleId: $data['source_rule_id'] ?? null,
                    strategy: $data['strategy'] ?? 'base',
                    metadata: $data['metadata'] ?? []
                );
            }
        }

        $seat = EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('seat_uid', $seatUid)
            ->with('priceTier')
            ->first();

        if (!$seat) {
            Log::warning("DefaultPricingEngine: Seat not found", [
                'event_seating_id' => $eventSeatingId,
                'seat_uid' => $seatUid,
            ]);

            return new PriceDecision(0, 0, null, 'not_found');
        }

        // Base price from tier or override
        $basePriceCents = $seat->price_cents_override ?? $seat->priceTier?->price_cents ?? 0;

        // Check for active price override
        $override = DynamicPriceOverride::where('event_seating_id', $eventSeatingId)
            ->where('seat_uid', $seatUid)
            ->active()
            ->first();

        if ($override) {
            $decision = new PriceDecision(
                basePriceCents: $basePriceCents,
                effectivePriceCents: $override->price_cents,
                sourceRuleId: $override->source_rule_id,
                strategy: 'override',
                metadata: [
                    'override_id' => $override->id,
                    'effective_from' => $override->effective_from->toIso8601String(),
                    'effective_to' => $override->effective_to?->toIso8601String(),
                ]
            );

            // SECURITY FIX: Use JSON instead of serialize
            Cache::put($cacheKey, json_encode($decision->toArray()), $this->cacheTtl);

            return $decision;
        }

        // No override: return base price
        $decision = new PriceDecision(
            basePriceCents: $basePriceCents,
            effectivePriceCents: $basePriceCents,
            strategy: 'base'
        );

        // SECURITY FIX: Use JSON instead of serialize
        Cache::put($cacheKey, json_encode($decision->toArray()), $this->cacheTtl);

        return $decision;
    }

    /**
     * Compute bulk prices for multiple seats
     */
    public function computeBulkPrices(int $eventSeatingId, array $seatUids): array
    {
        $decisions = [];

        foreach ($seatUids as $seatUid) {
            $decisions[$seatUid] = $this->computeEffectivePrice($eventSeatingId, $seatUid);
        }

        return $decisions;
    }

    /**
     * Apply dynamic repricing (stub implementation)
     */
    public function bulkReprice(int $eventSeatingId, string $scope, ?string $scopeRef = null): int
    {
        // Get active rules for this scope
        $rules = DynamicPricingRule::active()
            ->forScope($scope, $scopeRef)
            ->get();

        if ($rules->isEmpty()) {
            Log::info("DefaultPricingEngine: No active rules found", [
                'event_seating_id' => $eventSeatingId,
                'scope' => $scope,
                'scope_ref' => $scopeRef,
            ]);

            return 0;
        }

        $repriced = 0;

        foreach ($rules as $rule) {
            $repriced += $this->applyRule($eventSeatingId, $rule);
        }

        Log::info("DefaultPricingEngine: Bulk repricing completed", [
            'event_seating_id' => $eventSeatingId,
            'scope' => $scope,
            'seats_repriced' => $repriced,
        ]);

        return $repriced;
    }

    /**
     * Preview repricing without applying
     */
    public function previewRepricing(int $eventSeatingId, string $scope, ?string $scopeRef = null): array
    {
        $rules = DynamicPricingRule::active()
            ->forScope($scope, $scopeRef)
            ->get();

        $preview = [
            'rules_found' => $rules->count(),
            'estimated_changes' => 0,
            'rules' => [],
        ];

        foreach ($rules as $rule) {
            $preview['rules'][] = [
                'id' => $rule->id,
                'strategy' => $rule->strategy,
                'scope' => $rule->scope,
                'scope_ref' => $rule->scope_ref,
                'params' => $rule->params,
            ];
        }

        return $preview;
    }

    /**
     * Apply a single pricing rule (stub - extend for actual strategies)
     */
    private function applyRule(int $eventSeatingId, DynamicPricingRule $rule): int
    {
        // This is a stub implementation
        // In production, instantiate strategy class from config('seating.dynamic_pricing.strategies')
        // and call $strategy->apply($eventSeatingId, $rule)

        Log::info("DefaultPricingEngine: Would apply rule (stub)", [
            'rule_id' => $rule->id,
            'strategy' => $rule->strategy,
        ]);

        // For now, return 0 (no-op)
        // TODO: Implement actual strategy execution
        return 0;
    }

    /**
     * Clear price cache for event
     */
    public function clearCache(int $eventSeatingId): void
    {
        // In production, use cache tags or pattern matching
        Cache::flush();

        Log::info("DefaultPricingEngine: Cleared cache", [
            'event_seating_id' => $eventSeatingId,
        ]);
    }
}

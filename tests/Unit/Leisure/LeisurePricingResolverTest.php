<?php

namespace Tests\Unit\Leisure;

use App\Models\TicketType;
use App\Services\Leisure\LeisurePricingResolver;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LeisurePricingResolver. Uses TicketType instances built
 * in memory (no DB save) — the resolver depends only on attributes/casts.
 */
class LeisurePricingResolverTest extends TestCase
{
    private function makeTicket(array $attrs = []): TicketType
    {
        // Bypass casts for unit-test convenience — directly set attributes.
        $tt = new TicketType();
        $tt->setRawAttributes(array_merge([
            'price_cents' => 1000, // 10 RON
            'service_category' => 'rental',
        ], $attrs), true);
        return $tt;
    }

    public function test_base_price_no_rules(): void
    {
        $tt = $this->makeTicket();
        $resolver = new LeisurePricingResolver();
        $price = $resolver->resolvePrice($tt, Carbon::parse('2026-07-15'));
        $this->assertSame(1000, $price);
    }

    public function test_duration_variant_multiplier_applied(): void
    {
        $tt = $this->makeTicket([
            'leisure_duration_variants' => json_encode([
                ['duration_minutes' => 30, 'label' => '30 min', 'price_multiplier' => 1.0],
                ['duration_minutes' => 60, 'label' => '1h', 'price_multiplier' => 1.5],
                ['duration_minutes' => 120, 'label' => '2h', 'price_multiplier' => 2.5],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        $date = Carbon::parse('2026-07-15'); // Wednesday

        $this->assertSame(1000, $resolver->resolvePrice($tt, $date, 30));
        $this->assertSame(1500, $resolver->resolvePrice($tt, $date, 60));
        $this->assertSame(2500, $resolver->resolvePrice($tt, $date, 120));
    }

    public function test_weekend_percent_rule(): void
    {
        $tt = $this->makeTicket([
            'leisure_pricing_rules' => json_encode([
                ['label' => 'Weekend +25%', 'days' => [6, 7], 'type' => 'percent', 'value' => 25],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();

        // Friday → no rule
        $this->assertSame(1000, $resolver->resolvePrice($tt, Carbon::parse('2026-07-17')));
        // Saturday → +25%
        $this->assertSame(1250, $resolver->resolvePrice($tt, Carbon::parse('2026-07-18')));
        // Sunday → +25%
        $this->assertSame(1250, $resolver->resolvePrice($tt, Carbon::parse('2026-07-19')));
    }

    public function test_fixed_reduction_rule(): void
    {
        $tt = $this->makeTicket([
            'leisure_pricing_rules' => json_encode([
                ['label' => 'Weekday -2 RON', 'days' => [1, 2, 3, 4, 5], 'type' => 'fixed', 'value' => -2],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        // Wednesday → -2 RON = 800 cents
        $this->assertSame(800, $resolver->resolvePrice($tt, Carbon::parse('2026-07-15')));
        // Saturday → no rule
        $this->assertSame(1000, $resolver->resolvePrice($tt, Carbon::parse('2026-07-18')));
    }

    public function test_multiple_rules_are_cumulative(): void
    {
        $tt = $this->makeTicket([
            'leisure_pricing_rules' => json_encode([
                ['label' => 'Weekend +25%', 'days' => [6, 7], 'type' => 'percent', 'value' => 25],
                ['label' => 'Premium day +5 RON', 'days' => [6, 7], 'type' => 'fixed', 'value' => 5],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        // Saturday: 1000 * 1.25 = 1250, then +500 = 1750
        $this->assertSame(1750, $resolver->resolvePrice($tt, Carbon::parse('2026-07-18')));
    }

    public function test_duration_and_weekend_combined(): void
    {
        $tt = $this->makeTicket([
            'leisure_duration_variants' => json_encode([
                ['duration_minutes' => 60, 'price_multiplier' => 1.5],
            ]),
            'leisure_pricing_rules' => json_encode([
                ['days' => [6, 7], 'type' => 'percent', 'value' => 20],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        // Saturday, 1h: 1000 * 1.5 = 1500, then +20% = 1800
        $this->assertSame(1800, $resolver->resolvePrice($tt, Carbon::parse('2026-07-18'), 60));
    }

    public function test_season_within_range_applied(): void
    {
        $tt = $this->makeTicket([
            'leisure_seasons' => json_encode([
                [
                    'label' => 'High Season',
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-08-31',
                    'type' => 'percent',
                    'value' => 30,
                ],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        // Inside season: +30%
        $this->assertSame(1300, $resolver->resolvePrice($tt, Carbon::parse('2026-07-15')));
        // Before season: no change
        $this->assertSame(1000, $resolver->resolvePrice($tt, Carbon::parse('2026-06-30')));
        // After season: no change
        $this->assertSame(1000, $resolver->resolvePrice($tt, Carbon::parse('2026-09-01')));
    }

    public function test_negative_rule_does_not_produce_negative_price(): void
    {
        $tt = $this->makeTicket([
            'price_cents' => 500,
            'leisure_pricing_rules' => json_encode([
                ['days' => [1, 2, 3, 4, 5, 6, 7], 'type' => 'fixed', 'value' => -100],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        // 500 - 10000 = -9500, clamped to 0
        $this->assertSame(0, $resolver->resolvePrice($tt, Carbon::parse('2026-07-15')));
    }

    public function test_unknown_rule_type_ignored(): void
    {
        $tt = $this->makeTicket([
            'leisure_pricing_rules' => json_encode([
                ['days' => [1, 2, 3, 4, 5, 6, 7], 'type' => 'unknown_strategy', 'value' => 999],
            ]),
        ]);

        $resolver = new LeisurePricingResolver();
        $this->assertSame(1000, $resolver->resolvePrice($tt, Carbon::parse('2026-07-15')));
    }
}

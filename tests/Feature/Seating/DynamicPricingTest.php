<?php

namespace Tests\Feature\Seating;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use App\Models\Seating\DynamicPriceOverride;
use App\Services\Seating\Pricing\DefaultPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DynamicPricingTest
 *
 * Tests dynamic pricing engine and price overrides
 */
class DynamicPricingTest extends TestCase
{
    use RefreshDatabase;

    private DefaultPricingEngine $pricingEngine;
    private EventSeatingLayout $eventLayout;
    private PriceTier $baseTier;
    private EventSeat $testSeat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingEngine = app(DefaultPricingEngine::class);

        // Create test data
        $this->baseTier = PriceTier::factory()->create(['price_cents' => 5000]);

        $this->eventLayout = EventSeatingLayout::factory()->create();

        $this->testSeat = EventSeat::factory()->create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => 'TEST-SEAT-1',
            'price_tier_id' => $this->baseTier->id,
            'price_cents_override' => null,
        ]);
    }

    /** @test */
    public function returns_base_price_when_no_override_exists()
    {
        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $this->assertEquals(5000, $decision->basePriceCents);
        $this->assertEquals(5000, $decision->effectivePriceCents);
        $this->assertEquals('base', $decision->strategy);
        $this->assertFalse($decision->wasChanged());
    }

    /** @test */
    public function returns_override_price_when_active_override_exists()
    {
        // Create active price override
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 7500,
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addHour(),
        ]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $this->assertEquals(5000, $decision->basePriceCents);
        $this->assertEquals(7500, $decision->effectivePriceCents);
        $this->assertEquals('override', $decision->strategy);
        $this->assertTrue($decision->wasChanged());
        $this->assertEquals(2500, $decision->getDifferenceCents());
        $this->assertEquals(50.0, $decision->getChangePercentage());
    }

    /** @test */
    public function expired_override_does_not_apply()
    {
        // Create expired override
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 7500,
            'effective_from' => now()->subDays(2),
            'effective_to' => now()->subDay(),
        ]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        // Should return base price
        $this->assertEquals(5000, $decision->effectivePriceCents);
        $this->assertEquals('base', $decision->strategy);
    }

    /** @test */
    public function future_override_does_not_apply_yet()
    {
        // Create future override
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 7500,
            'effective_from' => now()->addDay(),
            'effective_to' => now()->addDays(2),
        ]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        // Should return base price
        $this->assertEquals(5000, $decision->effectivePriceCents);
        $this->assertEquals('base', $decision->strategy);
    }

    /** @test */
    public function can_compute_bulk_prices()
    {
        $seat2 = EventSeat::factory()->create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => 'TEST-SEAT-2',
            'price_tier_id' => $this->baseTier->id,
        ]);

        // Add override for seat 2
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $seat2->seat_uid,
            'price_cents' => 8000,
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addHour(),
        ]);

        $decisions = $this->pricingEngine->computeBulkPrices(
            $this->eventLayout->id,
            [$this->testSeat->seat_uid, $seat2->seat_uid]
        );

        $this->assertCount(2, $decisions);

        // Seat 1: base price
        $this->assertEquals(5000, $decisions[$this->testSeat->seat_uid]->effectivePriceCents);

        // Seat 2: override price
        $this->assertEquals(8000, $decisions[$seat2->seat_uid]->effectivePriceCents);
    }

    /** @test */
    public function price_cents_override_on_seat_takes_precedence()
    {
        // Set direct override on seat
        $this->testSeat->update(['price_cents_override' => 6000]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        // Should use seat override as base
        $this->assertEquals(6000, $decision->basePriceCents);
        $this->assertEquals(6000, $decision->effectivePriceCents);
    }

    /** @test */
    public function dynamic_override_applies_on_top_of_seat_override()
    {
        // Set seat override
        $this->testSeat->update(['price_cents_override' => 6000]);

        // Add dynamic override
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 8000,
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addHour(),
        ]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $this->assertEquals(6000, $decision->basePriceCents); // Seat override
        $this->assertEquals(8000, $decision->effectivePriceCents); // Dynamic override
        $this->assertEquals('override', $decision->strategy);
    }

    /** @test */
    public function price_decision_to_array_includes_all_fields()
    {
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 7500,
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addHour(),
            'source_rule_id' => 123,
        ]);

        $decision = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $array = $decision->toArray();

        $this->assertArrayHasKey('base_price_cents', $array);
        $this->assertArrayHasKey('effective_price_cents', $array);
        $this->assertArrayHasKey('difference_cents', $array);
        $this->assertArrayHasKey('change_percentage', $array);
        $this->assertArrayHasKey('was_changed', $array);
        $this->assertArrayHasKey('source_rule_id', $array);
        $this->assertArrayHasKey('strategy', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertEquals(5000, $array['base_price_cents']);
        $this->assertEquals(7500, $array['effective_price_cents']);
        $this->assertEquals(2500, $array['difference_cents']);
        $this->assertEquals(50.0, $array['change_percentage']);
        $this->assertTrue($array['was_changed']);
        $this->assertEquals(123, $array['source_rule_id']);
    }

    /** @test */
    public function pricing_engine_caches_results()
    {
        // First call
        $decision1 = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        // Add override (cache should prevent this from being seen)
        DynamicPriceOverride::create([
            'event_seating_id' => $this->eventLayout->id,
            'tenant_id' => $this->eventLayout->tenant_id,
            'seat_uid' => $this->testSeat->seat_uid,
            'price_cents' => 7500,
            'effective_from' => now()->subHour(),
            'effective_to' => now()->addHour(),
        ]);

        // Second call (should return cached base price)
        $decision2 = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $this->assertEquals(5000, $decision2->effectivePriceCents);

        // Clear cache
        $this->pricingEngine->clearCache($this->eventLayout->id);

        // Third call (should see override now)
        $decision3 = $this->pricingEngine->computeEffectivePrice(
            $this->eventLayout->id,
            $this->testSeat->seat_uid
        );

        $this->assertEquals(7500, $decision3->effectivePriceCents);
    }
}

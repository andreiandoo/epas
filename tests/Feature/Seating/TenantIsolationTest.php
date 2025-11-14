<?php

namespace Tests\Feature\Seating;

use App\Models\Seating\SeatingLayout;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use App\Models\Seating\SeatHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantIsolationTest
 *
 * Verifies that TenantScope correctly isolates data between tenants
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private int $tenant1Id;
    private int $tenant2Id;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two test tenants
        $this->tenant1Id = \App\Models\Tenant::factory()->create()->id;
        $this->tenant2Id = \App\Models\Tenant::factory()->create()->id;
    }

    /** @test */
    public function seating_layouts_are_isolated_by_tenant()
    {
        // Create layouts for both tenants
        $layout1 = SeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        $layout2 = SeatingLayout::factory()->create(['tenant_id' => $this->tenant2Id]);

        // Mock tenant 1 context
        $this->actingAsTenant($this->tenant1Id);

        // Should only see tenant 1 layouts
        $layouts = SeatingLayout::all();
        $this->assertCount(1, $layouts);
        $this->assertEquals($layout1->id, $layouts->first()->id);

        // Switch to tenant 2
        $this->actingAsTenant($this->tenant2Id);

        // Should only see tenant 2 layouts
        $layouts = SeatingLayout::all();
        $this->assertCount(1, $layouts);
        $this->assertEquals($layout2->id, $layouts->first()->id);
    }

    /** @test */
    public function event_seating_layouts_are_isolated_by_tenant()
    {
        $eventLayout1 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        $eventLayout2 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant2Id]);

        $this->actingAsTenant($this->tenant1Id);
        $this->assertCount(1, EventSeatingLayout::all());

        $this->actingAsTenant($this->tenant2Id);
        $this->assertCount(1, EventSeatingLayout::all());
    }

    /** @test */
    public function event_seats_are_isolated_by_tenant()
    {
        $eventLayout1 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        $eventLayout2 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant2Id]);

        EventSeat::factory()->count(5)->create([
            'event_seating_id' => $eventLayout1->id,
            'tenant_id' => $this->tenant1Id,
        ]);

        EventSeat::factory()->count(3)->create([
            'event_seating_id' => $eventLayout2->id,
            'tenant_id' => $this->tenant2Id,
        ]);

        $this->actingAsTenant($this->tenant1Id);
        $this->assertCount(5, EventSeat::all());

        $this->actingAsTenant($this->tenant2Id);
        $this->assertCount(3, EventSeat::all());
    }

    /** @test */
    public function price_tiers_are_isolated_by_tenant()
    {
        $tier1 = PriceTier::factory()->create(['tenant_id' => $this->tenant1Id]);
        $tier2 = PriceTier::factory()->create(['tenant_id' => $this->tenant2Id]);

        $this->actingAsTenant($this->tenant1Id);
        $this->assertCount(1, PriceTier::all());

        $this->actingAsTenant($this->tenant2Id);
        $this->assertCount(1, PriceTier::all());
    }

    /** @test */
    public function seat_holds_are_isolated_by_tenant()
    {
        $eventLayout1 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        $eventLayout2 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant2Id]);

        SeatHold::factory()->count(2)->create([
            'event_seating_id' => $eventLayout1->id,
            'tenant_id' => $this->tenant1Id,
        ]);

        SeatHold::factory()->count(4)->create([
            'event_seating_id' => $eventLayout2->id,
            'tenant_id' => $this->tenant2Id,
        ]);

        $this->actingAsTenant($this->tenant1Id);
        $this->assertCount(2, SeatHold::all());

        $this->actingAsTenant($this->tenant2Id);
        $this->assertCount(4, SeatHold::all());
    }

    /** @test */
    public function tenant_cannot_access_another_tenants_seats()
    {
        $eventLayout1 = EventSeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        $seat = EventSeat::factory()->create([
            'event_seating_id' => $eventLayout1->id,
            'tenant_id' => $this->tenant1Id,
        ]);

        // Try to access from tenant 2 context
        $this->actingAsTenant($this->tenant2Id);

        $foundSeat = EventSeat::find($seat->id);
        $this->assertNull($foundSeat);
    }

    /** @test */
    public function bypassing_tenant_scope_shows_all_records()
    {
        SeatingLayout::factory()->create(['tenant_id' => $this->tenant1Id]);
        SeatingLayout::factory()->create(['tenant_id' => $this->tenant2Id]);

        $this->actingAsTenant($this->tenant1Id);

        // With scope: should see 1
        $this->assertCount(1, SeatingLayout::all());

        // Without scope: should see 2
        $allLayouts = SeatingLayout::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->get();
        $this->assertCount(2, $allLayouts);
    }

    /** @test */
    public function tenant_id_is_auto_set_on_create()
    {
        $this->actingAsTenant($this->tenant1Id);

        $tier = PriceTier::create([
            'name' => 'VIP',
            'tier_code' => 'VIP',
            'price_cents' => 10000,
        ]);

        // tenant_id should be auto-set by TenantScope
        $this->assertEquals($this->tenant1Id, $tier->tenant_id);
    }

    /**
     * Helper to simulate tenant context
     */
    private function actingAsTenant(int $tenantId): void
    {
        // In a real app, this would be set by middleware/auth
        // For testing, we'll set it in the request
        request()->merge(['tenant_id' => $tenantId]);

        // Create a mock user for the tenant
        $user = new \stdClass();
        $user->tenant_id = $tenantId;

        $this->be($user);
    }
}

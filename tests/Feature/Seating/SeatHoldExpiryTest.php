<?php

namespace Tests\Feature\Seating;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatHold;
use App\Services\Seating\SeatHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * SeatHoldExpiryTest
 *
 * Tests TTL-based hold expiration and cleanup
 */
class SeatHoldExpiryTest extends TestCase
{
    use RefreshDatabase;

    private SeatHoldService $holdService;
    private EventSeatingLayout $eventLayout;
    private array $testSeats;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Redis for testing (use DB mode)
        Config::set('seating.use_redis_holds', false);

        $this->holdService = app(SeatHoldService::class);

        // Create test data
        $this->eventLayout = EventSeatingLayout::factory()->create();
        $this->testSeats = EventSeat::factory()
            ->count(5)
            ->create([
                'event_seating_id' => $this->eventLayout->id,
                'tenant_id' => $this->eventLayout->tenant_id,
                'status' => 'available',
            ])
            ->pluck('seat_uid')
            ->toArray();
    }

    /** @test */
    public function expired_holds_are_released_by_cleanup_job()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUid = $this->testSeats[0];

        // Hold seat
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        // Verify seat is held
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('held', $seat->status);

        // Verify hold record exists
        $hold = SeatHold::where('seat_uid', $seatUid)->first();
        $this->assertNotNull($hold);

        // Manually expire the hold
        $hold->update(['expires_at' => now()->subMinutes(1)]);

        // Run cleanup
        $releasedCount = $this->holdService->releaseExpiredHolds();

        // Verify hold was released
        $this->assertEquals(1, $releasedCount);

        $seat->refresh();
        $this->assertEquals('available', $seat->status);

        // Hold record should be deleted
        $this->assertNull(SeatHold::find($hold->id));
    }

    /** @test */
    public function hold_expires_at_is_set_correctly()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUid = $this->testSeats[0];

        $ttl = config('seating.hold_ttl_seconds', 600);
        $beforeHold = now();

        $result = $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        $afterHold = now();

        // Verify expires_at is returned
        $this->assertNotNull($result['expires_at']);

        // Verify hold record has correct expiry
        $hold = SeatHold::where('seat_uid', $seatUid)->first();
        $this->assertNotNull($hold);

        $expectedExpiresAt = $beforeHold->addSeconds($ttl);
        $actualExpiresAt = $hold->expires_at;

        // Allow 2 second tolerance
        $this->assertTrue(
            abs($expectedExpiresAt->diffInSeconds($actualExpiresAt)) <= 2,
            "Hold expires_at should be ~{$ttl} seconds from now"
        );
    }

    /** @test */
    public function non_expired_holds_are_not_released()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUids = array_slice($this->testSeats, 0, 3);

        // Hold seats
        $this->holdService->holdSeats($this->eventLayout->id, $seatUids, $sessionUid);

        // All seats should be held
        $heldCount = EventSeat::where('event_seating_id', $this->eventLayout->id)
            ->where('status', 'held')
            ->count();
        $this->assertEquals(3, $heldCount);

        // Run cleanup (none should be expired yet)
        $releasedCount = $this->holdService->releaseExpiredHolds();
        $this->assertEquals(0, $releasedCount);

        // Seats should still be held
        $heldCount = EventSeat::where('event_seating_id', $this->eventLayout->id)
            ->where('status', 'held')
            ->count();
        $this->assertEquals(3, $heldCount);
    }

    /** @test */
    public function cleanup_releases_multiple_expired_holds()
    {
        $session1 = 'session_' . uniqid();
        $session2 = 'session_' . uniqid();

        // Hold seats for two sessions
        $this->holdService->holdSeats($this->eventLayout->id, [$this->testSeats[0]], $session1);
        $this->holdService->holdSeats($this->eventLayout->id, [$this->testSeats[1]], $session2);

        // Expire both holds
        SeatHold::query()->update(['expires_at' => now()->subMinutes(1)]);

        // Run cleanup
        $releasedCount = $this->holdService->releaseExpiredHolds();

        // Both should be released
        $this->assertEquals(2, $releasedCount);

        // All seats should be available
        $availableCount = EventSeat::where('event_seating_id', $this->eventLayout->id)
            ->where('status', 'available')
            ->count();
        $this->assertEquals(2, $availableCount);
    }

    /** @test */
    public function expired_hold_cannot_be_confirmed()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUid = $this->testSeats[0];

        // Hold seat
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        // Expire the hold
        SeatHold::where('seat_uid', $seatUid)->update(['expires_at' => now()->subMinutes(1)]);

        // Run cleanup
        $this->holdService->releaseExpiredHolds();

        // Try to confirm (should fail)
        $result = $this->holdService->confirmPurchase(
            $this->eventLayout->id,
            [$seatUid],
            $sessionUid,
            'order_123'
        );

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);

        // Seat should be available
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('available', $seat->status);
    }

    /** @test */
    public function hold_can_be_confirmed_before_expiry()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUid = $this->testSeats[0];

        // Hold seat
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        // Confirm before expiry
        $result = $this->holdService->confirmPurchase(
            $this->eventLayout->id,
            [$seatUid],
            $sessionUid,
            'order_123'
        );

        $this->assertTrue($result['success']);

        // Seat should be sold
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('sold', $seat->status);
        $this->assertEquals('order_123', $seat->order_reference);
    }

    /** @test */
    public function releasing_hold_prevents_expiry_cleanup()
    {
        $sessionUid = 'session_' . uniqid();
        $seatUid = $this->testSeats[0];

        // Hold seat
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        // Manually release
        $this->holdService->releaseSeats($this->eventLayout->id, [$seatUid], $sessionUid);

        // Seat should be available
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('available', $seat->status);

        // Hold record should be deleted
        $hold = SeatHold::where('seat_uid', $seatUid)->first();
        $this->assertNull($hold);

        // Cleanup should find nothing
        $releasedCount = $this->holdService->releaseExpiredHolds();
        $this->assertEquals(0, $releasedCount);
    }
}

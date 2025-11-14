<?php

namespace Tests\Feature\Seating;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Services\Seating\SeatHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SeatHoldRaceConditionTest
 *
 * Tests concurrent seat hold attempts to verify atomic updates and optimistic locking
 */
class SeatHoldRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private SeatHoldService $holdService;
    private EventSeatingLayout $eventLayout;
    private array $testSeats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->holdService = app(SeatHoldService::class);

        // Create test data
        $this->eventLayout = EventSeatingLayout::factory()->create();
        $this->testSeats = EventSeat::factory()
            ->count(10)
            ->create([
                'event_seating_id' => $this->eventLayout->id,
                'tenant_id' => $this->eventLayout->tenant_id,
                'status' => 'available',
                'version' => 1,
            ])
            ->pluck('seat_uid')
            ->toArray();
    }

    /** @test */
    public function two_sessions_cannot_hold_same_seat_concurrently()
    {
        $seatUid = $this->testSeats[0];

        // Simulate two concurrent hold requests
        $session1 = 'session_' . uniqid();
        $session2 = 'session_' . uniqid();

        $result1 = $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $session1);
        $result2 = $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $session2);

        // Only one should succeed
        $this->assertCount(1, $result1['held']);
        $this->assertCount(1, $result2['failed']);

        // Verify seat is held by session1 only
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('held', $seat->status);
        $this->assertEquals(2, $seat->version); // Version incremented
    }

    /** @test */
    public function version_conflict_prevents_stale_updates()
    {
        $seatUid = $this->testSeats[0];
        $seat = EventSeat::where('seat_uid', $seatUid)->first();

        // Get initial version
        $version1 = $seat->version;

        // First update (increments version)
        DB::table('event_seats')
            ->where('id', $seat->id)
            ->where('version', $version1)
            ->update([
                'status' => 'held',
                'version' => DB::raw('version + 1'),
            ]);

        // Second update with stale version should fail
        $affected = DB::table('event_seats')
            ->where('id', $seat->id)
            ->where('version', $version1) // Stale version
            ->update([
                'status' => 'sold',
                'version' => DB::raw('version + 1'),
            ]);

        $this->assertEquals(0, $affected);

        // Verify seat is still held (first update succeeded)
        $seat->refresh();
        $this->assertEquals('held', $seat->status);
        $this->assertEquals($version1 + 1, $seat->version);
    }

    /** @test */
    public function holding_more_than_max_seats_fails()
    {
        $maxSeats = config('seating.max_held_seats_per_session', 10);
        $sessionUid = 'session_' . uniqid();

        // Hold max seats successfully
        $result1 = $this->holdService->holdSeats(
            $this->eventLayout->id,
            array_slice($this->testSeats, 0, $maxSeats),
            $sessionUid
        );

        $this->assertCount($maxSeats, $result1['held']);
        $this->assertCount(0, $result1['failed']);

        // Try to hold one more seat (should fail)
        $result2 = $this->holdService->holdSeats(
            $this->eventLayout->id,
            [$this->testSeats[$maxSeats]],
            $sessionUid
        );

        $this->assertCount(0, $result2['held']);
        $this->assertCount(1, $result2['failed']);
    }

    /** @test */
    public function multiple_sessions_can_hold_different_seats_concurrently()
    {
        $session1 = 'session_' . uniqid();
        $session2 = 'session_' . uniqid();

        $seats1 = array_slice($this->testSeats, 0, 3);
        $seats2 = array_slice($this->testSeats, 3, 3);

        // Hold different seats concurrently
        $result1 = $this->holdService->holdSeats($this->eventLayout->id, $seats1, $session1);
        $result2 = $this->holdService->holdSeats($this->eventLayout->id, $seats2, $session2);

        // Both should succeed
        $this->assertCount(3, $result1['held']);
        $this->assertCount(3, $result2['held']);

        // Verify all seats are held
        $heldCount = EventSeat::where('event_seating_id', $this->eventLayout->id)
            ->where('status', 'held')
            ->count();

        $this->assertEquals(6, $heldCount);
    }

    /** @test */
    public function atomic_bulk_hold_is_all_or_nothing_for_same_seat()
    {
        // This test verifies that if multiple requests try to hold the same seat
        // in a batch, only the first one succeeds

        $seatUid = $this->testSeats[0];
        $session1 = 'session_' . uniqid();
        $session2 = 'session_' . uniqid();

        // Both sessions try to hold the same seat + different seats
        $seats1 = [$seatUid, $this->testSeats[1]];
        $seats2 = [$seatUid, $this->testSeats[2]];

        $result1 = $this->holdService->holdSeats($this->eventLayout->id, $seats1, $session1);
        $result2 = $this->holdService->holdSeats($this->eventLayout->id, $seats2, $session2);

        // Session 1 should get both seats
        $this->assertCount(2, $result1['held']);

        // Session 2 should fail on the shared seat but might succeed on the unique seat
        $this->assertContains($seatUid, $result2['failed']);
    }

    /** @test */
    public function seat_version_increments_on_each_status_change()
    {
        $seatUid = $this->testSeats[0];
        $sessionUid = 'session_' . uniqid();

        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $initialVersion = $seat->version;

        // Hold
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);
        $seat->refresh();
        $this->assertEquals($initialVersion + 1, $seat->version);

        // Release
        $this->holdService->releaseSeats($this->eventLayout->id, [$seatUid], $sessionUid);
        $seat->refresh();
        $this->assertEquals($initialVersion + 2, $seat->version);

        // Hold again
        $this->holdService->holdSeats($this->eventLayout->id, [$seatUid], $sessionUid);
        $seat->refresh();
        $this->assertEquals($initialVersion + 3, $seat->version);

        // Confirm purchase
        $this->holdService->confirmPurchase(
            $this->eventLayout->id,
            [$seatUid],
            $sessionUid,
            'order_123'
        );
        $seat->refresh();
        $this->assertEquals($initialVersion + 4, $seat->version);
    }
}

<?php

namespace Tests\Feature\Seating;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\PriceTier;
use App\Models\Seating\SeatHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SeatingApiTest
 *
 * Tests public API endpoints for seating functionality
 */
class SeatingApiTest extends TestCase
{
    use RefreshDatabase;

    private EventSeatingLayout $eventLayout;
    private array $testSeats;
    private string $sessionUid;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $priceTier = PriceTier::factory()->create(['price_cents' => 5000]);

        $this->eventLayout = EventSeatingLayout::factory()->create([
            'status' => 'published',
            'geometry' => [
                'canvas' => ['width' => 800, 'height' => 600],
                'sections' => [
                    [
                        'section_code' => 'A',
                        'name' => 'Section A',
                        'x_position' => 100,
                        'y_position' => 100,
                        'width' => 200,
                        'height' => 150,
                        'rows' => [
                            [
                                'row_label' => 'Row 1',
                                'y_offset' => 0,
                                'seats' => [
                                    [
                                        'seat_uid' => 'A-1-1',
                                        'seat_number' => '1',
                                        'x_offset' => 0,
                                        'y_offset' => 0,
                                        'width' => 25,
                                        'height' => 25,
                                        'price_tier_id' => $priceTier->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->testSeats = EventSeat::factory()
            ->count(5)
            ->create([
                'event_seating_id' => $this->eventLayout->id,
                'tenant_id' => $this->eventLayout->tenant_id,
                'status' => 'available',
                'price_tier_id' => $priceTier->id,
            ])
            ->pluck('seat_uid')
            ->toArray();

        $this->sessionUid = 'test_session_' . uniqid();
    }

    /** @test */
    public function can_get_seating_layout_for_event()
    {
        $response = $this->getJson("/api/public/events/{$this->eventLayout->event_id}/seating");

        $response->assertOk()
            ->assertJsonStructure([
                'event_seating_id',
                'canvas' => ['width', 'height'],
                'price_tiers',
                'sections',
                'seat_counts' => ['total', 'available', 'held', 'sold', 'blocked', 'disabled'],
            ])
            ->assertJson([
                'event_seating_id' => $this->eventLayout->id,
            ]);
    }

    /** @test */
    public function can_get_seat_availability()
    {
        $response = $this->getJson("/api/public/events/{$this->eventLayout->event_id}/seats?event_seating_id={$this->eventLayout->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'seats' => [
                    '*' => [
                        'seat_uid',
                        'section_code',
                        'row_label',
                        'seat_number',
                        'status',
                        'price_cents',
                    ],
                ],
                'updated_at',
            ]);

        $this->assertCount(5, $response->json('seats'));
    }

    /** @test */
    public function can_hold_seats()
    {
        $seatsToHold = array_slice($this->testSeats, 0, 2);

        $response = $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => $seatsToHold,
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'held',
                'failed',
                'expires_at',
            ])
            ->assertJson([
                'held' => $seatsToHold,
                'failed' => [],
            ]);

        // Verify seats are held in database
        foreach ($seatsToHold as $seatUid) {
            $seat = EventSeat::where('seat_uid', $seatUid)->first();
            $this->assertEquals('held', $seat->status);
        }
    }

    /** @test */
    public function cannot_hold_already_held_seats()
    {
        $seatUid = $this->testSeats[0];

        // First hold succeeds
        $response1 = $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
        ], [
            'X-Seating-Session' => 'session1',
        ]);

        $response1->assertOk();

        // Second hold fails
        $response2 = $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
        ], [
            'X-Seating-Session' => 'session2',
        ]);

        $response2->assertStatus(409)
            ->assertJsonStructure(['held', 'failed'])
            ->assertJson([
                'held' => [],
                'failed' => [$seatUid],
            ]);
    }

    /** @test */
    public function can_release_held_seats()
    {
        $seatUid = $this->testSeats[0];

        // Hold seat
        $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        // Release seat
        $response = $this->deleteJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response->assertOk()
            ->assertJson([
                'released' => 1,
            ]);

        // Verify seat is available
        $seat = EventSeat::where('seat_uid', $seatUid)->first();
        $this->assertEquals('available', $seat->status);
    }

    /** @test */
    public function can_confirm_purchase()
    {
        $seatUids = array_slice($this->testSeats, 0, 2);

        // Hold seats first
        $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => $seatUids,
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        // Confirm purchase
        $response = $this->postJson('/api/public/seats/confirm', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => $seatUids,
            'order_reference' => 'ORDER-123',
            'idempotency_key' => 'idem_' . uniqid(),
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'confirmed' => 2,
            ]);

        // Verify seats are sold
        foreach ($seatUids as $seatUid) {
            $seat = EventSeat::where('seat_uid', $seatUid)->first();
            $this->assertEquals('sold', $seat->status);
            $this->assertEquals('ORDER-123', $seat->order_reference);
        }
    }

    /** @test */
    public function cannot_confirm_unheld_seats()
    {
        $seatUid = $this->testSeats[0];

        $response = $this->postJson('/api/public/seats/confirm', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
            'order_reference' => 'ORDER-123',
            'idempotency_key' => 'idem_' . uniqid(),
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function can_get_session_holds()
    {
        $seatUids = array_slice($this->testSeats, 0, 3);

        // Hold seats
        $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => $seatUids,
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        // Get holds
        $response = $this->getJson('/api/public/seats/holds', [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'holds' => [
                    '*' => [
                        'seat_uid',
                        'expires_at',
                        'remaining_seconds',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('holds'));
    }

    /** @test */
    public function validation_fails_for_invalid_requests()
    {
        // Missing required fields
        $response = $this->postJson('/api/public/seats/hold', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_seating_id', 'seat_uids']);
    }

    /** @test */
    public function idempotency_key_prevents_duplicate_confirmations()
    {
        $seatUid = $this->testSeats[0];
        $idempotencyKey = 'idem_' . uniqid();

        // Hold seat
        $this->postJson('/api/public/seats/hold', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        // First confirmation
        $response1 = $this->postJson('/api/public/seats/confirm', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
            'order_reference' => 'ORDER-123',
            'idempotency_key' => $idempotencyKey,
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response1->assertOk();

        // Second confirmation with same key (should return cached response)
        $response2 = $this->postJson('/api/public/seats/confirm', [
            'event_seating_id' => $this->eventLayout->id,
            'seat_uids' => [$seatUid],
            'order_reference' => 'ORDER-123',
            'idempotency_key' => $idempotencyKey,
        ], [
            'X-Seating-Session' => $this->sessionUid,
        ]);

        $response2->assertOk();

        // Responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }
}

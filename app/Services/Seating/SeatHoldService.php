<?php

namespace App\Services\Seating;

use App\Models\Seating\SeatHold;
use App\Models\Seating\EventSeat;
use App\Repositories\SeatInventoryRepository;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SeatHoldService
 *
 * Manages seat holds with 10-minute TTL using Redis (primary) and DB (fallback/audit)
 */
class SeatHoldService
{
    private bool $useRedis;
    private int $holdTtl;
    private string $keyPrefix;
    private int $maxHeldPerSession;

    public function __construct(private SeatInventoryRepository $inventory)
    {
        $this->useRedis = config('seating.use_redis_holds');
        $this->holdTtl = config('seating.hold_ttl_seconds');
        $this->keyPrefix = config('seating.redis.key_prefix');
        $this->maxHeldPerSession = config('seating.max_held_seats_per_session');
    }

    /**
     * Hold seats for a session
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @param string $sessionUid
     * @return array ['held' => [...], 'failed' => [...], 'expires_at' => ISO8601]
     */
    public function holdSeats(int $eventSeatingId, array $seatUids, string $sessionUid): array
    {
        // Check session hasn't exceeded max holds
        $currentHolds = $this->getSessionHoldCount($eventSeatingId, $sessionUid);

        if ($currentHolds + count($seatUids) > $this->maxHeldPerSession) {
            return [
                'held' => [],
                'failed' => array_map(fn($uid) => [
                    'seat_uid' => $uid,
                    'reason' => 'max_holds_exceeded',
                ], $seatUids),
                'expires_at' => null,
            ];
        }

        $held = [];
        $failed = [];
        $expiresAt = now()->addSeconds($this->holdTtl);

        DB::beginTransaction();
        try {
            foreach ($seatUids as $seatUid) {
                // Atomic update: available → held
                $updated = $this->inventory->atomicUpdateSeatsStatus(
                    $eventSeatingId,
                    [$seatUid],
                    'available',
                    'held'
                );

                if ($updated > 0) {
                    // Store in Redis (if enabled)
                    if ($this->useRedis) {
                        $this->storeHoldInRedis($eventSeatingId, $seatUid, $sessionUid);
                    }

                    // Always store in DB for audit/fallback
                    SeatHold::create([
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $seatUid,
                        'session_uid' => $sessionUid,
                        'expires_at' => $expiresAt,
                    ]);

                    $held[] = $seatUid;

                    Log::info("SeatHold: Held seat", [
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $seatUid,
                        'session_uid' => $sessionUid,
                        'expires_at' => $expiresAt,
                    ]);
                } else {
                    $failed[] = [
                        'seat_uid' => $seatUid,
                        'reason' => 'already_held_or_sold',
                    ];

                    Log::warning("SeatHold: Failed to hold seat", [
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $seatUid,
                        'reason' => 'already_held_or_sold',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("SeatHold: Exception during hold", [
                'event_seating_id' => $eventSeatingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return [
            'held' => $held,
            'failed' => $failed,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Release held seats
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @param string $sessionUid
     * @return array ['released' => [...]]
     */
    public function releaseSeats(int $eventSeatingId, array $seatUids, string $sessionUid): array
    {
        $released = [];

        DB::beginTransaction();
        try {
            // Verify session owns these holds
            $holds = SeatHold::where('event_seating_id', $eventSeatingId)
                ->whereIn('seat_uid', $seatUids)
                ->where('session_uid', $sessionUid)
                ->where('expires_at', '>', now())
                ->get();

            foreach ($holds as $hold) {
                // Atomic update: held → available
                $updated = $this->inventory->atomicUpdateSeatsStatus(
                    $eventSeatingId,
                    [$hold->seat_uid],
                    'held',
                    'available'
                );

                if ($updated > 0) {
                    // Remove from Redis
                    if ($this->useRedis) {
                        $this->removeHoldFromRedis($eventSeatingId, $hold->seat_uid);
                    }

                    // Delete hold record
                    $hold->delete();

                    $released[] = $hold->seat_uid;

                    Log::info("SeatHold: Released seat", [
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $hold->seat_uid,
                        'session_uid' => $sessionUid,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("SeatHold: Exception during release", [
                'event_seating_id' => $eventSeatingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return ['released' => $released];
    }

    /**
     * Confirm purchase (held/available → sold)
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @param string $sessionUid
     * @param int $paidAmountCents
     * @return array ['confirmed' => [...], 'failed' => [...]]
     */
    public function confirmPurchase(int $eventSeatingId, array $seatUids, string $sessionUid, int $paidAmountCents): array
    {
        $confirmed = [];
        $failed = [];

        DB::beginTransaction();
        try {
            foreach ($seatUids as $seatUid) {
                // Try to update from 'held' or 'available' to 'sold'
                // This handles both held seats and direct purchases
                $updated = EventSeat::where('event_seating_id', $eventSeatingId)
                    ->where('seat_uid', $seatUid)
                    ->whereIn('status', ['available', 'held'])
                    ->update([
                        'status' => 'sold',
                        'version' => DB::raw('version + 1'),
                        'last_change_at' => now(),
                    ]);

                if ($updated > 0) {
                    // Clean up Redis and holds
                    if ($this->useRedis) {
                        $this->removeHoldFromRedis($eventSeatingId, $seatUid);
                    }

                    SeatHold::where('event_seating_id', $eventSeatingId)
                        ->where('seat_uid', $seatUid)
                        ->delete();

                    $confirmed[] = $seatUid;

                    Log::info("SeatHold: Confirmed purchase", [
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $seatUid,
                        'session_uid' => $sessionUid,
                        'amount_cents' => $paidAmountCents,
                    ]);
                } else {
                    $failed[] = [
                        'seat_uid' => $seatUid,
                        'reason' => 'not_available',
                    ];

                    Log::warning("SeatHold: Failed to confirm purchase", [
                        'event_seating_id' => $eventSeatingId,
                        'seat_uid' => $seatUid,
                        'reason' => 'not_available',
                    ]);
                }
            }

            // If any failed, rollback all
            if (!empty($failed)) {
                DB::rollBack();

                return [
                    'confirmed' => [],
                    'failed' => array_merge(
                        $failed,
                        array_map(fn($uid) => ['seat_uid' => $uid, 'reason' => 'transaction_rollback'], $confirmed)
                    ),
                ];
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("SeatHold: Exception during confirm", [
                'event_seating_id' => $eventSeatingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return ['confirmed' => $confirmed, 'failed' => $failed];
    }

    /**
     * Get session's current hold count
     */
    public function getSessionHoldCount(int $eventSeatingId, string $sessionUid): int
    {
        return SeatHold::where('event_seating_id', $eventSeatingId)
            ->where('session_uid', $sessionUid)
            ->where('expires_at', '>', now())
            ->count();
    }

    /**
     * Get all active holds for a session
     */
    public function getSessionHolds(int $eventSeatingId, string $sessionUid): array
    {
        return SeatHold::where('event_seating_id', $eventSeatingId)
            ->where('session_uid', $sessionUid)
            ->where('expires_at', '>', now())
            ->pluck('seat_uid')
            ->toArray();
    }

    /**
     * Release expired holds (DB fallback mode)
     */
    public function releaseExpiredHolds(): int
    {
        $released = 0;

        $expiredHolds = SeatHold::where('expires_at', '<', now())->get();

        foreach ($expiredHolds as $hold) {
            DB::transaction(function () use ($hold, &$released) {
                $updated = EventSeat::where('event_seating_id', $hold->event_seating_id)
                    ->where('seat_uid', $hold->seat_uid)
                    ->where('status', 'held')
                    ->update([
                        'status' => 'available',
                        'version' => DB::raw('version + 1'),
                        'last_change_at' => now(),
                    ]);

                if ($updated > 0) {
                    $released++;

                    Log::info("SeatHold: Released expired hold", [
                        'event_seating_id' => $hold->event_seating_id,
                        'seat_uid' => $hold->seat_uid,
                        'expired_at' => $hold->expires_at,
                    ]);
                }

                $hold->delete();
            });
        }

        return $released;
    }

    /**
     * Store hold in Redis
     */
    private function storeHoldInRedis(int $eventSeatingId, string $seatUid, string $sessionUid): void
    {
        try {
            $key = $this->getRedisKey($eventSeatingId, $seatUid);
            Redis::setex($key, $this->holdTtl, $sessionUid);
        } catch (\Exception $e) {
            Log::warning("SeatHold: Failed to store in Redis", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove hold from Redis
     */
    private function removeHoldFromRedis(int $eventSeatingId, string $seatUid): void
    {
        try {
            $key = $this->getRedisKey($eventSeatingId, $seatUid);
            Redis::del($key);
        } catch (\Exception $e) {
            Log::warning("SeatHold: Failed to remove from Redis", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get Redis key for a seat hold
     */
    private function getRedisKey(int $eventSeatingId, string $seatUid): string
    {
        return "{$this->keyPrefix}:hold:{$eventSeatingId}:{$seatUid}";
    }
}

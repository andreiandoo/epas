<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A single (potentially pending) reservation for a slot of an Activity.
 *
 * Lifecycle:
 *   pending_payment → paid → confirmed → checked_in
 *                          ↘ cancelled
 *                          ↘ no_show
 *
 * One booking emits N tickets (one per participant) once paid — see
 * `tickets.activity_booking_id`. Hold semantics: while `held_until` is
 * in the future and status = pending_payment, the slot's capacity is
 * considered consumed by this booking. Expired holds are released by
 * the scheduled job introduced in A5.
 */
class ActivityBooking extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_CONFIRMED       = 'confirmed';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_CHECKED_IN      = 'checked_in';
    public const STATUS_NO_SHOW         = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_CHECKED_IN,
        self::STATUS_NO_SHOW,
    ];

    /**
     * Note the expired-hold sweep appends when it cancels a booking. The order
     * observer looks for it to tell a released hold (restored on a late
     * payment) from a booking cancelled by staff (left cancelled).
     */
    public const HOLD_EXPIRED_NOTE = 'Auto-cancelled: hold expired.';

    /**
     * Statuses that COUNT toward a slot's remaining capacity. Cancelled and
     * no_show bookings free up their seat. SlotResolver filters by this list.
     */
    public const CAPACITY_CONSUMING_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    protected $fillable = [
        'marketplace_client_id',
        'activity_id',
        'marketplace_customer_id',
        'order_id',
        'booking_date',
        'slot_start_time',
        'slot_end_time',
        'participants_count',
        'status',
        'total_cents',
        'commission_cents',
        'currency',
        'confirmation_code',
        'notes',
        'qr_payload',
        'held_until',
        'checked_in_at',
        'variant_id',
        'location_id',
        'marketplace_organizer_id',
        'package_booking_id',
        'end_date',
        'quantity',
        'unit_price_cents',
        'addons',
        'meta',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'slot_start_time' => 'datetime:H:i:s',
        'slot_end_time' => 'datetime:H:i:s',
        'participants_count' => 'integer',
        'total_cents' => 'integer',
        'commission_cents' => 'integer',
        'held_until' => 'datetime',
        'checked_in_at' => 'datetime',
        'end_date' => 'date',
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'addons' => 'array',
        'meta' => 'array',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ActivityVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ActivityLocation::class, 'location_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    /** The package booking this component belongs to. */
    public function packageBooking(): BelongsTo
    {
        return $this->belongsTo(self::class, 'package_booking_id');
    }

    /** Component bookings of a package booking. */
    public function componentBookings(): HasMany
    {
        return $this->hasMany(self::class, 'package_booking_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'activity_booking_id');
    }

    // ============================================================
    // BOOT — confirmation_code auto-generation
    // ============================================================

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            if (blank($booking->confirmation_code)) {
                $booking->confirmation_code = static::uniqueConfirmationCode(
                    $booking->marketplace_client_id
                );
            }
        });
    }

    /**
     * 10-char alphanumeric, scoped unique per marketplace. Easy to dictate
     * over the phone if QR is unreadable at the venue.
     */
    protected static function uniqueConfirmationCode(?int $marketplaceClientId): string
    {
        do {
            $code = strtoupper(Str::random(10));
            $exists = static::where('marketplace_client_id', $marketplaceClientId)
                ->where('confirmation_code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopeForSlot($query, int $activityId, $date, $startTime)
    {
        return $query
            ->where('activity_id', $activityId)
            ->where('booking_date', $date)
            ->where('slot_start_time', $startTime);
    }

    public function scopeConsumesCapacity($query)
    {
        return $query->whereIn('status', self::CAPACITY_CONSUMING_STATUSES);
    }

    public function scopeHeldExpired($query)
    {
        return $query
            ->where('status', self::STATUS_PENDING_PAYMENT)
            ->whereNotNull('held_until')
            ->where('held_until', '<', now());
    }
}

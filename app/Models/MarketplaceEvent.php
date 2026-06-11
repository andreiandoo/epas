<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MarketplaceEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'name',
        'slug',
        'description',
        'ticket_terms',
        'short_description',
        'starts_at',
        'ends_at',
        'doors_open_at',
        'venue_id',
        'venue_name',
        'venue_address',
        'venue_city',
        'marketplace_city_id',
        'category',
        'marketplace_event_category_id',
        'genre_ids',
        'artist_ids',
        'website_url',
        'facebook_url',
        'tags',
        'image',
        'cover_image',
        'gallery',
        'status',
        'is_public',
        'is_featured',
        'gamification_enabled',
        'points_per_purchase',
        'max_points_discount_percent',
        'capacity',
        'sales_start_at',
        'sales_end_at',
        'max_tickets_per_order',
        'target_price',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'tickets_sold',
        'revenue',
        'views',

        // Leisure venue fields
        'display_template',
        'venue_config',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'doors_open_at' => 'datetime',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'tags' => 'array',
        'genre_ids' => 'array',
        'artist_ids' => 'array',
        'gallery' => 'array',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'gamification_enabled' => 'boolean',
        'points_per_purchase' => 'decimal:2',
        'max_points_discount_percent' => 'decimal:2',
        'revenue' => 'decimal:2',
        'target_price' => 'decimal:2',
        'venue_config' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $baseSlug = Str::slug($event->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('marketplace_client_id', $event->marketplace_client_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $event->slug = $slug;
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(MarketplaceTicketType::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'marketplace_event_id');
    }

    public function dateCapacities(): HasMany
    {
        return $this->hasMany(MarketplaceEventDateCapacity::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCity::class, 'marketplace_city_id');
    }

    public function eventCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEventCategory::class, 'marketplace_event_category_id');
    }

    // =========================================
    // Status Checks
    // =========================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOnSale(): bool
    {
        if (!$this->isPublished()) {
            return false;
        }

        $now = now();

        if ($this->sales_start_at && $now < $this->sales_start_at) {
            return false;
        }

        if ($this->sales_end_at && $now > $this->sales_end_at) {
            return false;
        }

        // Leisure venues: on sale until ends_at (they run for the entire season)
        if ($this->isLeisureVenue()) {
            return !$this->ends_at || $this->ends_at > $now;
        }

        // Standard events: not on sale once started
        if ($this->starts_at && $this->starts_at < $now) {
            return false;
        }

        return true;
    }

    // =========================================
    // Leisure Venue Helpers
    // =========================================

    public function isLeisureVenue(): bool
    {
        return $this->display_template === 'leisure_venue';
    }

    /**
     * Get the active season for a specific date.
     * Returns season array or null if no season matches.
     * Supports both new seasons[] format and legacy operating_schedule format.
     */
    public function getSeasonForDate(string $date): ?array
    {
        $config = $this->venue_config ?? [];
        $seasons = $config['seasons'] ?? [];

        if (empty($seasons)) {
            // Legacy fallback: use operating_schedule as a single year-round season
            $schedule = $config['operating_schedule'] ?? [];
            if (!empty($schedule)) {
                return [
                    'name' => 'Default',
                    'start' => '01-01',
                    'end' => '12-31',
                    'schedule' => $schedule,
                    'last_entry' => null,
                ];
            }
            return null;
        }

        $md = Carbon::parse($date)->format('m-d'); // e.g. "07-15"

        foreach ($seasons as $season) {
            $start = $season['start'] ?? '01-01';
            $end = $season['end'] ?? '12-31';

            // Handle wrap-around seasons (e.g. Nov 01 - Mar 31)
            if ($start <= $end) {
                if ($md >= $start && $md <= $end) return $season;
            } else {
                if ($md >= $start || $md <= $end) return $season;
            }
        }

        return null;
    }

    /**
     * Check if a specific date is open for the leisure venue.
     */
    public function isDateOpen(string $date): bool
    {
        if (!$this->isLeisureVenue()) {
            return false;
        }

        $config = $this->venue_config ?? [];

        // Check closed dates
        $closedDates = $config['closed_dates'] ?? [];
        if (in_array($date, $closedDates)) {
            return false;
        }

        $season = $this->getSeasonForDate($date);
        if (!$season) {
            return false; // No season covers this date
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
        $schedule = $season['schedule'] ?? [];

        if (empty($schedule)) {
            return true; // No schedule = open every day
        }

        return isset($schedule[$dayOfWeek]) && $schedule[$dayOfWeek] !== null;
    }

    /**
     * Get operating hours for a specific date (from the matching season).
     */
    public function getOperatingHours(string $date): ?array
    {
        $season = $this->getSeasonForDate($date);
        if (!$season) return null;

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
        $schedule = $season['schedule'] ?? [];
        $hours = $schedule[$dayOfWeek] ?? null;

        if ($hours && !empty($season['last_entry'])) {
            $hours['last_entry'] = $season['last_entry'];
        }

        return $hours;
    }

    /**
     * Check if online sales should be blocked because last entry time has passed.
     */
    public function isPastLastEntry(string $date): bool
    {
        $season = $this->getSeasonForDate($date);
        if (!$season) return true;

        $lastEntry = $season['last_entry'] ?? null;
        if (!$lastEntry) return false;

        // Only relevant for today
        if ($date !== now()->format('Y-m-d')) return false;

        return now()->format('H:i') > $lastEntry;
    }

    /**
     * Calculate effective price for a ticket type on a given date.
     * Applies pricing rules from venue_config, then checks for date-specific override.
     */
    public function getEffectivePrice(MarketplaceTicketType $ticketType, string $date, ?float $dateOverride = null): float
    {
        // Date-level override takes absolute priority
        if ($dateOverride !== null) {
            return $dateOverride;
        }

        $basePrice = (float) $ticketType->price;
        $config = $this->venue_config ?? [];
        $pricingRules = $config['pricing_rules'] ?? [];

        if (empty($pricingRules)) {
            return $basePrice;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));

        foreach ($pricingRules as $rule) {
            $days = $rule['days'] ?? [];
            if (in_array($dayOfWeek, $days)) {
                $type = $rule['type'] ?? 'percent';
                $value = (float) ($rule['value'] ?? 0);

                if ($type === 'percent') {
                    return round($basePrice * (1 + $value / 100), 2);
                } elseif ($type === 'fixed') {
                    return round($basePrice + $value, 2);
                }
            }
        }

        return $basePrice;
    }

    public function hasAvailableTickets(): bool
    {
        return $this->ticketTypes()
            ->where('status', 'on_sale')
            ->where('is_visible', true)
            ->where(function ($query) {
                $query->whereNull('quantity')
                    ->orWhereRaw('quantity > quantity_sold + quantity_reserved');
            })
            ->exists();
    }

    // =========================================
    // Actions
    // =========================================

    /**
     * Submit event for review
     */
    public function submitForReview(): void
    {
        $this->update([
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approve event
     */
    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => 'published',
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject event
     */
    public function reject(string $reason): void
    {
        $this->update([
            'status' => 'draft',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Cancel event
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Update sales stats
     */
    public function updateStats(): void
    {
        $completedOrders = $this->orders()->where('status', 'completed');

        $this->update([
            'tickets_sold' => $completedOrders->withCount('tickets')->get()->sum('tickets_count'),
            'revenue' => $completedOrders->sum('total'),
        ]);
    }

    // =========================================
    // Helpers
    // =========================================

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/' . $this->image);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) {
            return null;
        }

        return str_starts_with($this->cover_image, 'http')
            ? $this->cover_image
            : asset('storage/' . $this->cover_image);
    }

    public function getVenueDisplayNameAttribute(): string
    {
        $venue = $this->venue;
        if ($venue) {
            $name = $venue->getTranslation('name');
            if ($name) {
                return $name;
            }
        }

        return $this->venue_name ?? 'TBA';
    }

    public function getVenueDisplayCityAttribute(): ?string
    {
        return $this->venue?->city ?? $this->venue_city;
    }

    public function getPriceRangeAttribute(): array
    {
        $ticketTypes = $this->ticketTypes()
            ->where('status', 'on_sale')
            ->where('is_visible', true)
            ->get();

        if ($ticketTypes->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => $ticketTypes->min('price'),
            'max' => $ticketTypes->max('price'),
        ];
    }
}

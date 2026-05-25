<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Bookable activity with a weekly recurring schedule.
 *
 * The Event analogue, intentionally separate: Activities don't have a
 * fixed date, don't go through fiscal declaration (avize), don't carry
 * artists, and book by (date + time slot) rather than by a calendar
 * event. See `MarketplaceCategory` for the shared category taxonomy.
 *
 * Activated per marketplace via the `activities-module` microservice.
 * If the toggle is off for a given marketplace, no public route, no
 * admin resource, no API endpoint touches this model.
 */
class Activity extends Model
{
    use HasFactory, SoftDeletes, Translatable;

    public array $translatable = [
        'title',
        'subtitle',
        'short_description',
        'description',
        'seo_body_title',
        'seo_body',
    ];

    protected $fillable = [
        // tenancy
        'marketplace_client_id',
        'marketplace_organizer_id',

        // location + taxonomy
        'venue_id',
        'marketplace_city_id',
        'marketplace_category_id',
        'marketplace_subcategory_id',

        // identity
        'title',
        'slug',
        'subtitle',
        'short_description',
        'description',

        // operating timing
        'duration_minutes',
        'slot_interval_minutes',
        'buffer_minutes',
        'capacity_per_slot',
        'min_participants',
        'max_participants',

        // booking window
        'booking_lead_time_hours',
        'booking_max_advance_days',
        'cancellation_policy',

        // media
        'cover_image_url',
        'hero_image_url',
        'gallery',

        // content blocks
        'included_items',
        'not_included',
        'requirements',
        'meeting_point',
        'languages_offered',

        // intent flags + audience
        'is_indoor',
        'is_outdoor',
        'is_kid_friendly',
        'is_accessible',
        'is_weather_sensitive',
        'age_min',
        'age_max',
        'difficulty_level',

        // publishing
        'is_published',
        'is_featured',
        'is_homepage_featured',
        'is_category_featured',
        'is_city_featured',

        // SEO
        'seo',
        'seo_body_title',
        'seo_body',
        'faqs',

        // cached aggregates
        'cheapest_price_cents',
        'views_count',
        'interested_count',
        'next_session_at',
        'has_session_today',
        'has_session_tomorrow',
        'has_session_this_weekend',
    ];

    protected $casts = [
        // translatables
        'title' => 'array',
        'subtitle' => 'array',
        'short_description' => 'array',
        'description' => 'array',
        'seo_body_title' => 'array',
        'seo_body' => 'array',

        // pure json
        'gallery' => 'array',
        'included_items' => 'array',
        'not_included' => 'array',
        'requirements' => 'array',
        'languages_offered' => 'array',
        'seo' => 'array',
        'faqs' => 'array',

        // numerics
        'duration_minutes' => 'integer',
        'slot_interval_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'capacity_per_slot' => 'integer',
        'min_participants' => 'integer',
        'max_participants' => 'integer',
        'booking_lead_time_hours' => 'integer',
        'booking_max_advance_days' => 'integer',
        'age_min' => 'integer',
        'age_max' => 'integer',
        'cheapest_price_cents' => 'integer',
        'views_count' => 'integer',
        'interested_count' => 'integer',

        // flags
        'is_indoor' => 'boolean',
        'is_outdoor' => 'boolean',
        'is_kid_friendly' => 'boolean',
        'is_accessible' => 'boolean',
        'is_weather_sensitive' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'is_homepage_featured' => 'boolean',
        'is_category_featured' => 'boolean',
        'is_city_featured' => 'boolean',
        'has_session_today' => 'boolean',
        'has_session_tomorrow' => 'boolean',
        'has_session_this_weekend' => 'boolean',

        // datetime
        'next_session_at' => 'datetime',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

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

    public function city(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCity::class, 'marketplace_city_id');
    }

    /**
     * Shared with Events — see MarketplaceCategory (alias of MarketplaceEventCategory).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_subcategory_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ActivitySchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('sort_order');
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ActivityScheduleException::class)
            ->orderBy('exception_date');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ActivityVariant::class)
            ->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ActivityBooking::class);
    }

    // ============================================================
    // BOOT — slug auto-generation (same pattern as Event)
    // ============================================================

    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            if (blank($activity->slug)) {
                $title = is_array($activity->title)
                    ? ($activity->title['ro'] ?? $activity->title['en'] ?? '')
                    : ($activity->title ?? '');

                if (filled($title)) {
                    $activity->slug = static::uniqueSlug(
                        Str::slug($title),
                        $activity->marketplace_client_id
                    );
                }
            }
        });
    }

    protected static function uniqueSlug(string $base, ?int $marketplaceClientId = null): string
    {
        $slug = $base ?: 'activity';
        $i = 1;

        $query = static::where('slug', $slug);
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        while ($query->exists()) {
            $slug = $base . '-' . $i++;
            $query = static::where('slug', $slug);
            if ($marketplaceClientId) {
                $query->where('marketplace_client_id', $marketplaceClientId);
            }
        }

        return $slug;
    }
}

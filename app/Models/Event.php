<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Concerns\IsLeisureVenue;
use App\Support\Translatable;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory;
    use Translatable;
    use LogsActivity;
    use IsLeisureVenue;

    /**
     * Sanitize SEO data to prevent malformed UTF-8 encoding errors.
     */
    public function setSeoAttribute($value): void
    {
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) {
                if (is_string($item)) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                }
            });
        }
        $this->attributes['seo'] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : $value;
    }

    public array $translatable = ['title', 'subtitle', 'short_description', 'description', 'ticket_terms', 'thank_you_message'];

    /**
     * Sanitize the per-event post-purchase message through HTMLPurifier
     * before it's persisted. This is the single choke-point for BOTH the
     * Filament admin and the organizer TinyMCE — either surface writes to
     * this attribute, so no matter where the HTML came from we know it's
     * been through the `thank_you_message` HTMLPurifier profile (see
     * config/purifier.php): whitelisted tags/attrs/CSS, iframe locked to
     * YouTube + Vimeo, script tags / inline event handlers / data: URIs
     * dropped silently.
     *
     * Input can be either an array `['ro' => '<p>...</p>', 'en' => '...']`
     * or a JSON-encoded string of that same shape (some callers stringify
     * before passing).
     */
    public function setThankYouMessageAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : ['ro' => $value];
        }

        if (!is_array($value)) {
            $this->attributes['thank_you_message'] = null;
            return;
        }

        $clean = [];
        foreach ($value as $locale => $html) {
            $html = is_string($html) ? $html : '';
            $html = trim($html);
            if ($html === '') {
                continue;
            }
            try {
                $clean[$locale] = \Mews\Purifier\Facades\Purifier::clean($html, 'thank_you_message');
            } catch (\Throwable $e) {
                // Purifier misconfig should not block the whole save.
                // Fall back to strip_tags with a safe minimal whitelist so
                // we NEVER persist unsanitized HTML if the library dies.
                \Log::warning('HTMLPurifier failed on thank_you_message', [
                    'event_id' => $this->id,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
                $clean[$locale] = strip_tags($html, '<p><br><b><strong><i><em><u><a><ul><ol><li>');
            }
        }

        $this->attributes['thank_you_message'] = empty($clean) ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Encrypt the Zoom / meeting passcode at rest. Keeps a leaked DB
     * dump inert unless the attacker also has APP_KEY. The mutator is
     * idempotent (won't double-encrypt an already-encrypted value) and
     * the accessor falls back to plaintext if the value isn't
     * decryptable (defensive against partial writes / key rotation).
     */
    public function setOnlinePasscodeAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['online_passcode'] = null;
            return;
        }
        try {
            // Try to decrypt first — if it succeeds, the value is already
            // encrypted (someone re-saved the model without touching the
            // field) and we don't want to double-wrap it.
            \Illuminate\Support\Facades\Crypt::decryptString((string) $value);
            $this->attributes['online_passcode'] = (string) $value;
        } catch (\Throwable $e) {
            $this->attributes['online_passcode'] = \Illuminate\Support\Facades\Crypt::encryptString((string) $value);
        }
    }

    public function getOnlinePasscodeAttribute($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $value);
        } catch (\Throwable $e) {
            // Value predates the mutator or APP_KEY rotated — return
            // as-is so at least the operator can see + rewrite it.
            return (string) $value;
        }
    }

    /**
     * Sanitize the optional online_instructions HTML the organizer
     * enters (dresscode virtual, tips audio, browser recomandat, etc.)
     * through the same HTMLPurifier profile as system_update.
     */
    public function setOnlineInstructionsAttribute($value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $this->attributes['online_instructions'] = null;
            return;
        }
        try {
            $this->attributes['online_instructions'] = \Mews\Purifier\Facades\Purifier::clean(
                (string) $value,
                'system_update'
            );
        } catch (\Throwable $e) {
            \Log::warning('HTMLPurifier failed on online_instructions', [
                'event_id' => $this->id,
                'error'    => $e->getMessage(),
            ]);
            $this->attributes['online_instructions'] = strip_tags(
                (string) $value,
                '<p><br><b><strong><i><em><u><a><ul><ol><li>'
            );
        }
    }

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'venue_id',
        'seating_layout_id',
        'ticket_template_id',
        'commission_mode',
        'commission_rate',
        'use_fixed_commission',
        'title',
        'slug',
        'event_series',
        'duration_mode',

        // Parent/child event relationships
        'parent_id',
        'is_template',
        'occurrence_number',

        // Tour & Theater
        'tour_id',
        'season_id',
        'repertoire_id',

        // flags
        'is_sold_out', 'is_cancelled', 'cancel_reason',
        'is_postponed', 'postponed_date', 'postponed_start_time', 'postponed_door_time', 'postponed_end_time', 'postponed_reason',
        'door_sales_only', 'is_promoted', 'promoted_until', 'is_featured',
        'is_homepage_featured', 'is_general_featured', 'is_category_featured', 'is_city_featured', 'is_published', 'submitted_at', 'rejected_at', 'rejection_reason', 'access_password', 'redirect_url',
        'generate_fomo', 'fomo_displayed_remaining', 'fomo_displayed_remaining_updated_at',
        'has_custom_related', 'custom_related_event_ids',
        'homepage_featured_image', 'featured_image',

        // single day
        'event_date', 'start_time', 'door_time', 'end_time',

        // range
        'range_start_date', 'range_end_date', 'range_start_time', 'range_end_time',

        // multi-day json
        'multi_slots',

        // recurring
        'recurring_frequency', 'recurring_start_date', 'recurring_start_time',
        'recurring_door_time', 'recurring_end_time', 'recurring_weekday',
        'recurring_week_of_month', 'recurring_count',

        // media
        'poster_url', 'poster_original_filename',
        'hero_image_url', 'hero_image_original_filename',

        // location & links
        'suggested_venue_name',
        'address', 'website_url', 'facebook_url', 'event_website_url', 'video_url',
        'marketplace_city_id', 'marketplace_event_category_id', 'manifestation_type',

        // content
        'short_description', 'description', 'ticket_terms', 'thank_you_message',

        // online event (Zoom / custom livestream) — gated by the
        // 'zoom-integration' microservice on the marketplace_client.
        'is_online',
        'online_provider',
        'online_meeting_url',
        'online_passcode',
        'online_instructions',
        'online_lobby_opens_minutes_before',
        'online_capacity_hint',

        // seo json
        'seo',

        // admin
        'admin_notes',

        // marketplace pricing & tracking
        'target_price',
        'general_stock',
        'general_quota',
        'organizer_notifications',
        'marketplace_tax_registry_id',
        'views_count',
        'interested_count',

        // ticket display options
        'enable_ticket_groups',
        'enable_ticket_perks',

        // leisure venue
        'display_template',
        'venue_config',

        // intent attributes (manual flags set by organizer for SEO landing pages)
        'is_indoor',
        'is_outdoor',
        'is_kid_friendly',
        'is_accessible',
        'is_weather_sensitive',
        'min_age',
        'max_age',
        'audience_tags',

        // intent aggregates (recomputed by events:refresh-intent-aggregates)
        'cheapest_price_cents',
        'next_session_at',
        'has_session_today',
        'has_session_tomorrow',
        'has_session_this_weekend',
    ];

    protected $casts = [
        // translatables
        'title'             => 'array',
        'subtitle'          => 'array',
        'short_description' => 'array',
        'description'       => 'array',
        'ticket_terms'      => 'array',
        'thank_you_message' => 'array',
        // online event
        'is_online'                          => 'bool',
        'online_lobby_opens_minutes_before'  => 'int',
        'online_capacity_hint'               => 'int',

        // date-only
        'event_date'           => 'date',
        'range_start_date'     => 'date',
        'range_end_date'       => 'date',
        'recurring_start_date' => 'date',
        'postponed_date'       => 'date',
        'promoted_until'       => 'date',

        // flags
        'is_sold_out'       => 'bool',
        'is_cancelled'      => 'bool',
        'is_postponed'      => 'bool',
        'door_sales_only'   => 'bool',
        'is_promoted'       => 'bool',
        'is_featured'       => 'bool',
        'is_homepage_featured'  => 'bool',
        'is_general_featured'   => 'bool',
        'is_category_featured'  => 'bool',
        'generate_fomo'         => 'bool',
        'fomo_displayed_remaining' => 'int',
        'fomo_displayed_remaining_updated_at' => 'datetime',
        'is_city_featured'      => 'bool',
        'is_published'          => 'bool',
        'submitted_at'          => 'datetime',
        'rejected_at'           => 'datetime',
        'has_custom_related'    => 'bool',
        'custom_related_event_ids' => 'array',
        'is_template'       => 'bool',
        'enable_ticket_groups' => 'bool',
        'enable_ticket_perks'  => 'bool',

        // commission
        'commission_rate'   => 'decimal:2',
        'use_fixed_commission' => 'bool',

        // json
        'multi_slots'       => 'array',
        'organizer_notifications' => 'array',
        'seo'               => 'array',
        'venue_config'      => 'array',

        // marketplace pricing & tracking
        'target_price'      => 'decimal:2',
        'views_count'       => 'integer',
        'interested_count'  => 'integer',

        // intent attributes
        'is_indoor'             => 'bool',
        'is_outdoor'            => 'bool',
        'is_kid_friendly'       => 'bool',
        'is_accessible'         => 'bool',
        'is_weather_sensitive'  => 'bool',
        'min_age'               => 'integer',
        'max_age'               => 'integer',
        'audience_tags'         => 'array',

        // intent aggregates
        'cheapest_price_cents'      => 'integer',
        'next_session_at'           => 'datetime',
        'has_session_today'         => 'bool',
        'has_session_tomorrow'      => 'bool',
        'has_session_this_weekend'  => 'bool',
    ];

    /**
     * Boot the model and add event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate event_series after creating an event
        static::created(function ($event) {
            if (empty($event->event_series)) {
                $event->event_series = 'AMB-' . $event->id;
                $event->saveQuietly(); // Save without triggering events again
            }
        });

        // Defensive: events.slug is NOT NULL, but historical rows may have null slug
        // and the Filament form lets users blank the field. Derive from title or id.
        static::saving(function ($event) {
            if (empty($event->slug)) {
                $title = $event->title;
                if (is_array($title)) {
                    $title = $title['ro'] ?? $title['en'] ?? reset($title) ?? '';
                }
                $base = \Illuminate\Support\Str::slug((string) ($title ?: 'event'));
                $suffix = $event->id ?: ('tmp-' . uniqid());
                $event->slug = ($base ?: 'event') . '-' . $suffix;
            }
        });

        // Date-mode field hygiene: when an event is saved, NULL out the
        // date/time/slot columns that don't belong to its `duration_mode`.
        // Otherwise a duplicate-then-edit flow (clone a single-day event,
        // switch to range, save) leaves the original `event_date` in the
        // row and downstream accessors that fall back on it surface the
        // wrong date in lists / homepage / emails.
        //
        // Only the three primary modes are touched. `recurring` parent
        // templates are left alone (they use a different field family
        // and child events carry per-occurrence event_date separately).
        static::saving(function ($event) {
            $map = match ($event->duration_mode) {
                'single_day' => [
                    'range_start_date', 'range_end_date',
                    'range_start_time', 'range_end_time',
                    'multi_slots',
                ],
                'range' => [
                    'event_date', 'start_time', 'door_time', 'end_time',
                    'multi_slots',
                ],
                'multi_day' => [
                    'event_date', 'start_time', 'door_time', 'end_time',
                    'range_start_date', 'range_end_date',
                    'range_start_time', 'range_end_time',
                ],
                default => null, // recurring / custom / null → leave untouched
            };
            if (! $map) {
                return;
            }
            foreach ($map as $field) {
                if ($event->{$field} !== null) {
                    $event->{$field} = null;
                }
            }
        });
    }

    /**
     * Get a plain string name (first available locale from translatable title).
     * Used by relationships in admin panel and API.
     */
    public function getNameAttribute(): string
    {
        $title = $this->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? reset($title) ?? '';
        }

        return (string) ($title ?? '');
    }

    /* Tour Relation */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /* Season & Repertoire (Theater) */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function repertoire(): BelongsTo
    {
        return $this->belongsTo(Repertoire::class);
    }

    /**
     * Tenant artists performing in this event (cast/distribution).
     */
    public function tenantArtists(): BelongsToMany
    {
        return $this->belongsToMany(TenantArtist::class, 'event_tenant_artist')
            ->withPivot(['role_in_event', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Merch products linked to this event.
     */
    public function merchProducts(): BelongsToMany
    {
        return $this->belongsToMany(MerchProduct::class, 'merch_product_event')
            ->withPivot(['price_override_cents', 'stock_override', 'is_bundle_only', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /* Parent/Child Event Relations */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Event::class, 'parent_id')->orderBy('occurrence_number');
    }

    /**
     * Check if this event has child events
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if this event is a child of another event
     */
    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    /* Core Relations */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function marketplaceCity(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCity::class);
    }

    public function marketplaceEventCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEventCategory::class);
    }

    /* Taxonomies (normalized) */
    public function eventTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EventType::class,
            'event_event_type',
            'event_id',
            'event_type_id'
        );
    }

    public function eventGenres(): BelongsToMany
    {
        return $this->belongsToMany(
            EventGenre::class,
            'event_event_genre',
            'event_id',
            'event_genre_id'
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            EventTag::class,
            'event_event_tag',
            'event_id',
            'event_tag_id'
        );
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'event_artist',
            'event_id',
            'artist_id'
        )
        ->withPivot(['sort_order', 'is_headliner', 'is_co_headliner'])
        ->orderByPivot('sort_order');
    }

    /**
     * Get custom related events
     */
    public function customRelatedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'id')
            ->whereIn('id', $this->custom_related_event_ids ?? []);
    }

    /**
     * Get the custom related events as a collection
     */
    public function getCustomRelatedEventsAttribute()
    {
        if (!$this->has_custom_related || empty($this->custom_related_event_ids)) {
            return collect();
        }

        return Event::whereIn('id', $this->custom_related_event_ids)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /* Tickets */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Ticket::class,
            \App\Models\TicketType::class,
            'event_id',        // Foreign key de pe ticket_types -> events
            'ticket_type_id',  // Foreign key de pe tickets -> ticket_types
            'id',              // Local key pe events
            'id'               // Local key pe ticket_types
        );
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Venue::class);
    }

    public function seatingLayout(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Seating\SeatingLayout::class);
    }

    public function ticketTemplate(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TicketTemplate::class);
    }

    /**
     * Get all service orders for this event
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'marketplace_event_id');
    }

    /**
     * Get active featuring service orders (paid promotions)
     */
    public function activeFeaturingOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'marketplace_event_id')
            ->where('service_type', ServiceOrder::TYPE_FEATURING)
            ->where('status', ServiceOrder::STATUS_ACTIVE);
    }

    /**
     * Get available seating sections for this event
     * Based on the selected seating layout
     */
    public function getAvailableSeatingSections()
    {
        if (!$this->seating_layout_id) {
            return collect();
        }

        return $this->seatingLayout?->sections()
            ->where('section_type', 'standard')
            ->orderBy('display_order')
            ->get() ?? collect();
    }

    /**
     * Human-readable date label for the event, picked from whichever
     * fields belong to the row's `duration_mode`. Mirrors the formatting
     * used by the events list column so the same string surfaces in
     * lists, page titles, emails, etc.
     *
     *   single_day → "15 Mai 2026"
     *   range      → "15-20 Sep 2026" (same month) /
     *                "15 Sep - 5 Oct 2026" (same year) /
     *                "15 Dec 2026 - 5 Ian 2027" (cross-year)
     *   multi_day  → "15-20 Sep 2026" (first/last slot)
     *   recurring  → uses recurring_start_date if set, otherwise null
     *   anything missing → null (so callers can omit the line entirely)
     */
    public function displayDateLabel(): ?string
    {
        if ($this->duration_mode === 'range') {
            $start = $this->range_start_date;
            $end = $this->range_end_date;
            if ($start && $end) {
                if ($start->format('m Y') === $end->format('m Y')) {
                    return $start->format('d') . '-' . $end->format('d M Y');
                }
                if ($start->format('Y') === $end->format('Y')) {
                    return $start->format('d M') . ' - ' . $end->format('d M Y');
                }
                return $start->format('d M Y') . ' - ' . $end->format('d M Y');
            }
            if ($start) {
                return 'din ' . $start->format('d M Y');
            }
            return null;
        }

        if ($this->duration_mode === 'multi_day' && ! empty($this->multi_slots)) {
            $slots = collect($this->multi_slots)->pluck('date')->filter()->sort()->values();
            if ($slots->count() >= 2) {
                $first = \Carbon\Carbon::parse($slots->first());
                $last = \Carbon\Carbon::parse($slots->last());
                if ($first->format('m Y') === $last->format('m Y')) {
                    return $first->format('d') . '-' . $last->format('d M Y');
                }
                return $first->format('d M') . ' - ' . $last->format('d M Y');
            }
            if ($slots->count() === 1) {
                return \Carbon\Carbon::parse($slots->first())->format('d M Y');
            }
            return null;
        }

        if ($this->duration_mode === 'recurring' && $this->recurring_start_date) {
            return \Carbon\Carbon::parse($this->recurring_start_date)->format('d M Y');
        }

        // single_day (or unset mode with event_date present)
        return $this->event_date?->format('d M Y');
    }

    /**
     * Get effective commission mode (event's, marketplace organizer's, or tenant's default)
     */
    public function getEffectiveCommissionMode(): string
    {
        if ($this->commission_mode !== null) {
            return $this->commission_mode;
        }

        // For marketplace events, check organizer's mode
        if ($this->marketplace_organizer_id) {
            return $this->marketplaceOrganizer?->getEffectiveCommissionMode() ?? 'included';
        }

        return $this->tenant->commission_mode ?? 'included';
    }

    /**
     * Get effective commission rate (event's, marketplace organizer's, or tenant's default)
     */
    public function getEffectiveCommissionRate(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        // For marketplace events, check organizer's rate
        if ($this->marketplace_organizer_id) {
            return $this->marketplaceOrganizer?->getEffectiveCommissionRate() ?? 5.00;
        }

        return (float) ($this->tenant->commission_rate ?? 5.00);
    }

    /**
     * Get start date based on duration mode
     */
    public function getStartDateAttribute()
    {
        return match ($this->duration_mode) {
            'single_day' => $this->event_date,
            'range' => $this->range_start_date,
            'multi_day' => isset($this->multi_slots[0]['date']) ? \Carbon\Carbon::parse($this->multi_slots[0]['date']) : null,
            'recurring' => $this->recurring_start_date,
            default => $this->event_date ?? $this->starts_at,
        };
    }

    /**
     * Get end date based on duration mode
     */
    public function getEndDateAttribute()
    {
        return match ($this->duration_mode) {
            'range' => $this->range_end_date,
            'multi_day' => !empty($this->multi_slots)
                ? \Carbon\Carbon::parse(collect($this->multi_slots)->last()['date'])
                : null,
            default => null,
        };
    }

    /**
     * Get the effective end datetime (date + time) for the event.
     *
     * Times (end_time / range_end_time / multi_slot end_time / postponed_end_time)
     * are entered by organizers in the marketplace's local timezone (default
     * Europe/Bucharest for tenant-only events). We parse with that TZ so the
     * absolute UTC instant is correct — Carbon comparisons (>, isPast, etc.)
     * operate on instants, so callers don't need to think about TZ.
     */
    public function getEffectiveEndDatetime(): ?\Carbon\Carbon
    {
        $tz = \App\Support\MarketplaceTz::tz($this->marketplaceClient);

        // Postponed events: the "effective" end is the new postponed date +
        // postponed_end_time (or postponed_start_time as cutoff). This makes
        // isPast()/isUpcoming()/MarkEndedEvents skip postponed events whose
        // new date is still in the future, even if the original event_date
        // is past.
        if ($this->is_postponed && $this->postponed_date) {
            $time = $this->postponed_end_time
                ?? $this->postponed_start_time
                ?? '23:59';
            return \Carbon\Carbon::parse(
                $this->postponed_date->format('Y-m-d') . ' ' . $time,
                $tz
            );
        }

        $endDate = $this->end_date;
        $endTime = match ($this->duration_mode) {
            'single_day' => $this->end_time,
            'range' => $this->range_end_time,
            'multi_day' => !empty($this->multi_slots)
                ? (collect($this->multi_slots)->last()['end_time'] ?? '23:59')
                : null,
            default => $this->end_time,
        };

        // If we have an end_date, use it with end_time
        if ($endDate) {
            $time = $endTime ?? '23:59';
            return \Carbon\Carbon::parse($endDate->format('Y-m-d') . ' ' . $time, $tz);
        }

        // For single_day without explicit end_date, use start_date with end_time
        if ($this->duration_mode === 'single_day' && $this->start_date) {
            $time = $this->end_time ?? '23:59';
            return \Carbon\Carbon::parse($this->start_date->format('Y-m-d') . ' ' . $time, $tz);
        }

        return null;
    }

    /**
     * Check if the event has passed
     */
    public function isPast(): bool
    {
        $effectiveEnd = $this->getEffectiveEndDatetime();

        if ($effectiveEnd) {
            return $effectiveEnd->isPast();
        }

        // Fallback: if we only have start_date, event is past if start_date is before today
        if ($this->start_date) {
            return $this->start_date->endOfDay()->isPast();
        }

        return false;
    }

    /**
     * Check if the event is upcoming (not past and not cancelled)
     */
    public function isUpcoming(): bool
    {
        return !$this->isPast() && !$this->is_cancelled;
    }

    /**
     * Scope for upcoming events (not past, not cancelled)
     */
    public function scopeUpcoming($query)
    {
        $now = now();

        return $query
            ->where(function ($q) {
                $q->where('is_cancelled', false)
                  ->orWhereNull('is_cancelled');
            })
            ->where(function ($q) use ($now) {
                // Range events: end_date + range_end_time must be in future
                $q->where(function ($q2) use ($now) {
                    $q2->where('duration_mode', 'range')
                       ->where('range_end_date', '>=', $now->toDateString());
                })
                // Single day events: event_date + end_time must be in future
                ->orWhere(function ($q2) use ($now) {
                    $q2->where('duration_mode', 'single_day')
                       ->where('event_date', '>=', $now->toDateString());
                })
                // Multi-day or other modes
                ->orWhere(function ($q2) use ($now) {
                    $q2->whereNotIn('duration_mode', ['range', 'single_day'])
                       ->where(function ($q3) use ($now) {
                           $q3->whereRaw(
                               DB::getDriverName() === 'pgsql'
                                   ? "multi_slots->0->>'date' >= ?"
                                   : "JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[0].date')) >= ?",
                               [$now->toDateString()]
                           )
                              ->orWhereNull('multi_slots');
                       });
                });
            });
    }

    /**
     * Scope for past events
     */
    public function scopePast($query)
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now) {
                // Range events: end_date is in past
                $q->where(function ($q2) use ($now) {
                    $q2->where('duration_mode', 'range')
                       ->where('range_end_date', '<', $now->toDateString());
                })
                // Single day events: event_date is in past
                ->orWhere(function ($q2) use ($now) {
                    $q2->where('duration_mode', 'single_day')
                       ->where('event_date', '<', $now->toDateString());
                });
            });
    }

    /**
     * Configure activity logging
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['views_count', 'updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Event {$eventName}");
    }

    /**
     * Add tenant_id to activity properties for scoping
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }

    /* Analytics Relations */

    /**
     * Get milestones for this event
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(EventMilestone::class)->orderBy('start_date', 'desc');
    }

    /**
     * Get daily analytics records for this event
     */
    public function analyticsDaily(): HasMany
    {
        return $this->hasMany(EventAnalyticsDaily::class)->orderBy('date', 'desc');
    }

    /**
     * Get hourly analytics records for this event
     */
    public function analyticsHourly(): HasMany
    {
        return $this->hasMany(EventAnalyticsHourly::class)->orderBy('date', 'desc')->orderBy('hour', 'desc');
    }

    /**
     * Get weekly analytics records for this event
     */
    public function analyticsWeekly(): HasMany
    {
        return $this->hasMany(EventAnalyticsWeekly::class)->orderBy('week_start', 'desc');
    }

    /**
     * Get monthly analytics records for this event
     */
    public function analyticsMonthly(): HasMany
    {
        return $this->hasMany(EventAnalyticsMonthly::class)->orderBy('month_start', 'desc');
    }

    /**
     * Get active ad campaigns for this event
     */
    public function activeAdCampaigns(): HasMany
    {
        return $this->hasMany(EventMilestone::class)
            ->whereIn('type', EventMilestone::AD_CAMPAIGN_TYPES)
            ->where('is_active', true)
            ->whereNotNull('budget');
    }

    /**
     * Get orders for this event
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    /**
     * Calculate total revenue for this event.
     *
     * Re-aligned 2026-06-14 (second pass) with the admin panel's "net
     * organizator" number on /marketplace/events/{id}/edit?tab=vanzari.
     *
     * Verified for event 4365:
     *   ticket_face_value = sum(per_type: count(valid|used) × tt.price)
     *                     = 102×70 + 55×60 + 25×50 + 24×40 + 12×0
     *                     = 7140 + 3300 + 1250 + 960 + 0
     *                     = 12650
     *   discount          = Order.sum(discount_amount) on paid|confirmed|
     *                       completed orders = 148
     *   revenue           = 12650 − 148 = 12502   ✓ matches admin exactly
     *
     * Invitations contribute their face value (0 lei for Invitatie ticket
     * type 10775) — so a free invitation correctly adds 0 to revenue but
     * its count is included in tickets_sold (status valid|used). POS
     * ticket types with zero sold contribute 0.
     *
     * sale_price takes precedence over price when set (mirrors all other
     * places in the codebase that compute "what the customer would have
     * been charged").
     */
    public function getTotalRevenueAttribute(): float
    {
        // PERF P2/8 — routes through EventStatsCache so 100 dashboard hits
        // on the same event in 60s share one computation. The service
        // wraps the identical query logic and falls back to a live
        // compute on any cache error (Redis down, exception, etc).
        return (float) (\App\Services\EventStatsCache::get($this->id)['total_revenue'] ?? 0.0);
    }

    /**
     * Calculate total tickets sold for this event.
     *
     * Aligned 2026-06-14 with the admin panel's "Bilete valide" number on
     * /marketplace/events/{id}/edit?tab=vanzari. Admin formula
     * (EventStatistics::getTicketStats line 416):
     *
     *   $validTickets = $valid + $used;   // status in ('valid', 'used')
     *
     * Includes invitations (status='valid') on top of paid online tickets
     * (also valid/used). For event 4365: 206 paid online + 12 invitations
     * = 218 — matches admin exactly.
     *
     * NOTE: we deliberately use `whereIn(status, [valid,used])` instead of
     * `where(is_cancelled, false)` because the `is_cancelled` boolean is
     * inconsistently set on legacy cancelled tickets (some carry
     * status='cancelled' without is_cancelled=true), which produced 248
     * instead of 218 on the same event.
     */
    public function getTotalTicketsSoldAttribute(): int
    {
        // PERF P2/8 — see comment on getTotalRevenueAttribute. Same
        // service, same key — the two attributes share one cache read
        // when called together (Laravel attribute accessor cache makes
        // this even cheaper within a single request).
        return (int) (\App\Services\EventStatsCache::get($this->id)['total_tickets_sold'] ?? 0);
    }

    /**
     * Check if this event has at least one ticket type that will expire within 24h
     * AND no replacement (no other active ticket beyond 24h, no scheduled future ticket).
     * Uses pre-loaded ticketTypes collection if available (no extra queries).
     */
    public function hasExpiringTicketsWithoutReplacement(): bool
    {
        if (!$this->is_published || $this->is_cancelled) return false;

        $eventDate = $this->event_date ?? $this->range_start_date;
        if ($eventDate && \Carbon\Carbon::parse($eventDate)->isPast()) return false;

        $now = now('Europe/Bucharest');
        $cutoff = $now->copy()->addHours(24);

        $tickets = $this->relationLoaded('ticketTypes') ? $this->ticketTypes : $this->ticketTypes()->get();

        $expiringSoon = $tickets->contains(function ($tt) use ($now, $cutoff) {
            if ($tt->status !== 'active') return false;
            $au = $tt->active_until ? \Carbon\Carbon::parse($tt->active_until) : null;
            $se = $tt->sales_end_at ? \Carbon\Carbon::parse($tt->sales_end_at) : null;
            return ($au && $au->between($now, $cutoff)) || ($se && $se->between($now, $cutoff));
        });

        if (!$expiringSoon) return false;

        $hasReplacement = $tickets->contains(function ($tt) use ($cutoff, $now) {
            // Active and not expiring soon
            if ($tt->status === 'active') {
                $au = $tt->active_until ? \Carbon\Carbon::parse($tt->active_until) : null;
                $se = $tt->sales_end_at ? \Carbon\Carbon::parse($tt->sales_end_at) : null;
                $auOk = !$au || $au->gt($cutoff);
                $seOk = !$se || $se->gt($cutoff);
                if ($auOk && $seOk) return true;
            }
            // Scheduled for future activation
            if ($tt->scheduled_at && \Carbon\Carbon::parse($tt->scheduled_at)->gt($now)) return true;
            return false;
        });

        return !$hasReplacement;
    }

    /**
     * Get cached list of events with expiring tickets without replacement.
     * Uses 1 SQL query + 15min cache.
     */
    public static function expiringWithoutReplacement(?int $marketplaceClientId = null): \Illuminate\Support\Collection
    {
        $cacheKey = 'expiring_tickets_alert_' . ($marketplaceClientId ?? 'all');

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(15), function () use ($marketplaceClientId) {
            $now = now('Europe/Bucharest');
            $cutoff = $now->copy()->addHours(24);

            $query = self::query()
                ->with('ticketTypes')
                ->where('is_published', true)
                ->where('is_cancelled', false)
                ->where(function ($q) use ($now) {
                    $q->whereNull('event_date')->orWhere('event_date', '>=', $now->toDateString());
                })
                ->whereHas('ticketTypes', function ($q) use ($now, $cutoff) {
                    $q->where('status', 'active')
                        ->where(function ($q2) use ($now, $cutoff) {
                            $q2->where(function ($q3) use ($now, $cutoff) {
                                $q3->whereNotNull('active_until')
                                   ->whereBetween('active_until', [$now, $cutoff]);
                            })->orWhere(function ($q3) use ($now, $cutoff) {
                                $q3->whereNotNull('sales_end_at')
                                   ->whereBetween('sales_end_at', [$now, $cutoff]);
                            });
                        });
                });

            if ($marketplaceClientId) {
                $query->where('marketplace_client_id', $marketplaceClientId);
            }

            // Filter in PHP using the helper (using preloaded ticketTypes — no extra queries)
            return $query->get()->filter(fn ($e) => $e->hasExpiringTicketsWithoutReplacement())->values();
        });
    }

    /**
     * Get currency from marketplace client or default to RON
     */
    public function getCurrencyAttribute(): string
    {
        return $this->marketplaceClient?->currency ?? 'RON';
    }

    /**
     * Human-readable label for the online provider — used on emails +
     * the /join gate page. Add new labels here when a new provider is
     * supported; keep the enum + label list in one place.
     */
    public function getOnlineProviderLabelAttribute(): string
    {
        return match ((string) $this->online_provider) {
            'zoom'          => 'Zoom',
            'google_meet'   => 'Google Meet',
            'teams'         => 'Microsoft Teams',
            'custom'        => 'Livestream',
            default         => 'Online',
        };
    }

    /**
     * Timestamp when the /join gate becomes active for THIS event —
     * `start_time - online_lobby_opens_minutes_before` minutes. Returns
     * null if the event doesn't have a resolvable start time yet.
     */
    public function getOnlineLobbyOpensAtAttribute(): ?\Carbon\Carbon
    {
        $starts = $this->resolveStartsAt();
        if (! $starts) return null;
        $mins = (int) ($this->online_lobby_opens_minutes_before ?? 15);
        return $starts->copy()->subMinutes($mins);
    }

    /**
     * True while the visitor can currently open the /join gate:
     *   lobby_opens_at ≤ now ≤ (end_time OR start_time + 4h)
     * The 4h tail gives late joiners a chance without keeping the door
     * open forever (a stale link shouldn't remain valid for weeks).
     */
    public function isOnlineJoinable(?\Carbon\Carbon $at = null): bool
    {
        if (! $this->is_online) return false;

        $at = $at ?: now();
        $lobby = $this->online_lobby_opens_at;
        if (! $lobby) return false;

        $starts = $this->resolveStartsAt();
        $ends = $this->resolveEndsAt() ?? ($starts ? $starts->copy()->addHours(4) : null);
        if (! $ends) return false;

        return $at->between($lobby, $ends);
    }

    /**
     * Resolve the actual start Carbon for online-join gating.
     * Tries event_date + start_time first, then range_start_date +
     * range_start_time, then the multi-slot first entry.
     */
    protected function resolveStartsAt(): ?\Carbon\Carbon
    {
        if (! empty($this->event_date) && ! empty($this->start_time)) {
            return \Carbon\Carbon::parse($this->event_date->format('Y-m-d') . ' ' . $this->start_time);
        }
        if (! empty($this->range_start_date) && ! empty($this->range_start_time)) {
            return \Carbon\Carbon::parse($this->range_start_date->format('Y-m-d') . ' ' . $this->range_start_time);
        }
        if (! empty($this->multi_slots) && is_array($this->multi_slots)) {
            $first = $this->multi_slots[0] ?? null;
            if ($first && !empty($first['date']) && !empty($first['start_time'])) {
                return \Carbon\Carbon::parse($first['date'] . ' ' . $first['start_time']);
            }
        }
        return null;
    }

    protected function resolveEndsAt(): ?\Carbon\Carbon
    {
        if (! empty($this->event_date) && ! empty($this->end_time)) {
            return \Carbon\Carbon::parse($this->event_date->format('Y-m-d') . ' ' . $this->end_time);
        }
        if (! empty($this->range_end_date) && ! empty($this->range_end_time)) {
            return \Carbon\Carbon::parse($this->range_end_date->format('Y-m-d') . ' ' . $this->range_end_time);
        }
        return null;
    }

    /**
     * Get total capacity from all ticket types
     */
    public function getTotalCapacityAttribute(): int
    {
        // If capacity is set directly on event, use it
        if ($this->capacity) {
            return $this->capacity;
        }

        // Otherwise sum from ticket types (-1 means unlimited)
        if ($this->ticketTypes()->where('quota_total', '<', 0)->exists()) {
            return -1;
        }
        return $this->ticketTypes()->sum('quota_total') ?: 0;
    }

    /**
     * Get remaining shared pool capacity (non-independent tickets only).
     * Returns null if general_quota is not set (no shared pool).
     */
    public function getSharedPoolRemainingAttribute(): ?int
    {
        if ($this->general_quota === null) {
            return null;
        }

        $soldNonIndependent = $this->ticketTypes()
            ->where('is_independent_stock', false)
            ->sum('quota_sold');

        return max(0, $this->general_quota - (int) $soldNonIndependent);
    }

    /**
     * Get available quantity for a specific ticket type, respecting shared pool.
     */
    public function getAvailableForTicketType(TicketType $ticketType): int
    {
        $ownAvailable = $ticketType->quota_total < 0
            ? PHP_INT_MAX
            : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

        // No shared pool or independent stock → own availability only
        if ($this->general_quota === null || $ticketType->is_independent_stock) {
            return $ownAvailable;
        }

        $poolRemaining = $this->shared_pool_remaining;
        return min($ownAvailable, $poolRemaining);
    }

    /**
     * Calculate sold percentage
     */
    public function getSoldPercentageAttribute(): float
    {
        $capacity = $this->total_capacity;
        if ($capacity <= 0) {
            return 0;
        }

        return round(($this->total_tickets_sold / $capacity) * 100, 2);
    }

    /**
     * Get days until event (negative if past)
     */
    public function getDaysUntilAttribute(): int
    {
        $startDate = $this->start_date;
        if (!$startDate) {
            return 0;
        }

        return now()->startOfDay()->diffInDays($startDate, false);
    }

    /**
     * Get event status label
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_cancelled) {
            return 'Cancelled';
        }
        if ($this->is_postponed) {
            return 'Postponed';
        }
        if ($this->is_sold_out) {
            return 'Sold Out';
        }
        if ($this->isPast()) {
            return 'Completed';
        }
        if ($this->door_sales_only) {
            return 'Door Sales';
        }
        return 'On Sale';
    }

    /**
     * Find-or-create the auto-provisioned POS test ticket type.
     *
     * Purpose: every non-leisure event carries a single, capped test-only
     * ticket type so organizers can smoke-test the Tixello mobile POS
     * (sell, print, scan) before their real event. Priced at 10 lei, a
     * fixed quota of 10 tickets, hidden from the public site + all
     * revenue reports via meta.is_test = true.
     *
     * Orders that ring these up sit on source = 'pos_test' — the same
     * filter chain that already excludes 'external_import' / 'pos_app'
     * from decont math extends to cover them (see SalesBreakdownService).
     *
     * Leisure events opt out by convention: their POS/POS-ticket model
     * is a completely separate flow (slots, per-society issuers) and
     * doesn't share this ticket_types table shape.
     */
    public function ensureTestTicketType(): ?TicketType
    {
        if (($this->display_template ?? null) === 'leisure_venue') {
            return null;
        }

        return $this->ticketTypes()
            ->where(function ($q) {
                $q->whereRaw("(meta->>'is_test')::boolean = true")
                    ->orWhere('name', 'Test POS');
            })
            ->first()
            ?? $this->ticketTypes()->create([
                'name' => 'Test POS',
                'currency' => $this->currency ?? 'RON',
                // Virtual mutator on TicketType converts `price` to
                // price_cents, so 10 lei writes 1000 cents.
                'price' => 10,
                // Fixed pool of 10 — enough for a full smoke test of
                // sell + print + scan without polluting the event's
                // quota planning.
                'capacity' => 10,
                'quota_sold' => 0,
                'is_active' => true,
                'is_entry_ticket' => true,
                'is_refundable' => false,
                'is_declarable' => false,
                'is_subscription' => false,
                'min_per_order' => 1,
                'max_per_order' => 10,
                // Commission stays at 0/inherit — test sales don't
                // feed the payout pipeline anyway; whatever is written
                // here never gets read by the exclusion path.
                'commission_type' => null,
                'meta' => [
                    'is_test' => true,
                    'hide_from_public' => true,
                    'auto_provisioned' => true,
                    'auto_provisioned_at' => now()->toIso8601String(),
                ],
                'sort_order' => 9999,
                'color' => '#8B5CF6',
            ]);
    }
}

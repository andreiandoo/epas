<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Support\Translatable;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory;
    use Translatable;
    use LogsActivity;

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

    public array $translatable = ['title', 'subtitle', 'short_description', 'description', 'ticket_terms'];

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
        'is_homepage_featured', 'is_general_featured', 'is_category_featured', 'is_city_featured', 'is_published', 'submitted_at', 'access_password',
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
        'address', 'website_url', 'facebook_url', 'event_website_url',
        'marketplace_city_id', 'marketplace_event_category_id', 'manifestation_type',

        // content
        'short_description', 'description', 'ticket_terms',

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
    ];

    protected $casts = [
        // translatables
        'title'             => 'array',
        'subtitle'          => 'array',
        'short_description' => 'array',
        'description'       => 'array',
        'ticket_terms'      => 'array',

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
        'is_city_featured'      => 'bool',
        'is_published'          => 'bool',
        'submitted_at'          => 'datetime',
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
        ->orderByPivot('sort_order')
        ->select('artists.*');
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
     * Get the effective end datetime (date + time) for the event
     */
    public function getEffectiveEndDatetime(): ?\Carbon\Carbon
    {
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
            return \Carbon\Carbon::parse($endDate->format('Y-m-d') . ' ' . $time);
        }

        // For single_day without explicit end_date, use start_date with end_time
        if ($this->duration_mode === 'single_day' && $this->start_date) {
            $time = $this->end_time ?? '23:59';
            return \Carbon\Carbon::parse($this->start_date->format('Y-m-d') . ' ' . $time);
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
            ->logOnly(['title', 'slug', 'event_date', 'is_cancelled', 'is_postponed', 'is_sold_out', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Event {$eventName}")
            ->useLogName('tenant');
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
     * Calculate total revenue for this event (paid/confirmed/completed orders)
     */
    public function getTotalRevenueAttribute(): float
    {
        // Sum revenue only from valid/used tickets in paid/confirmed/completed orders
        // This excludes individually cancelled/refunded tickets
        return (float) \App\Models\Ticket::where('event_id', $this->id)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'confirmed', 'completed']);
            })
            ->sum('price');
    }

    /**
     * Calculate total tickets sold for this event (only valid/used tickets)
     */
    public function getTotalTicketsSoldAttribute(): int
    {
        return (int) \App\Models\Ticket::where('event_id', $this->id)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'confirmed', 'completed']);
            })
            ->count();
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
}

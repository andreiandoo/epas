<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Support\Translatable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory;
    use Translatable;
    use LogsActivity;

    public array $translatable = ['title', 'subtitle', 'short_description', 'description', 'ticket_terms'];

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'venue_id',
        'ticket_template_id',
        'commission_mode',
        'commission_rate',
        'title',
        'slug',
        'duration_mode',

        // Parent/child event relationships
        'parent_id',
        'is_template',
        'occurrence_number',

        // flags
        'is_sold_out', 'is_cancelled', 'cancel_reason',
        'is_postponed', 'postponed_date', 'postponed_start_time', 'postponed_door_time', 'postponed_end_time', 'postponed_reason',
        'door_sales_only', 'is_promoted', 'promoted_until', 'is_featured',
        'is_homepage_featured', 'is_general_featured', 'is_category_featured',

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
        'poster_url', 'hero_image_url',

        // location & links
        'address', 'website_url', 'facebook_url', 'event_website_url',
        'marketplace_city_id', 'marketplace_event_category_id',

        // content
        'short_description', 'description', 'ticket_terms',

        // seo json
        'seo',

        // marketplace pricing & tracking
        'target_price',
        'views_count',
        'interested_count',
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
        'is_template'       => 'bool',

        // commission
        'commission_rate'   => 'decimal:2',

        // json
        'multi_slots'       => 'array',
        'seo'               => 'array',

        // marketplace pricing & tracking
        'target_price'      => 'decimal:2',
        'views_count'       => 'integer',
        'interested_count'  => 'integer',
    ];

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
        );
    }

    /* Tickets */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
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

    public function ticketTemplate(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TicketTemplate::class);
    }

    /**
     * Get effective commission mode (event's or tenant's default)
     */
    public function getEffectiveCommissionMode(): string
    {
        if ($this->commission_mode !== null) {
            return $this->commission_mode;
        }

        return $this->tenant->commission_mode ?? 'included';
    }

    /**
     * Get effective commission rate (event's or tenant's default)
     */
    public function getEffectiveCommissionRate(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
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
            'multi_day' => isset($this->multi_slots) && count($this->multi_slots) > 0
                ? \Carbon\Carbon::parse(end($this->multi_slots)['date'])
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
            'multi_day' => isset($this->multi_slots) && count($this->multi_slots) > 0
                ? (end($this->multi_slots)['end_time'] ?? '23:59')
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
                           $q3->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[0].date')) >= ?", [$now->toDateString()])
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
}

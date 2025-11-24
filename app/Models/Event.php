<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Translatable;

class Event extends Model
{
    use HasFactory;
    use Translatable;

    public array $translatable = ['title', 'subtitle', 'short_description', 'description', 'slug', 'ticket_terms'];

    protected $fillable = [
        'tenant_id',
        'commission_mode',
        'commission_rate',
        'title',
        'slug',
        'duration_mode',

        // flags
        'is_sold_out', 'is_cancelled', 'cancel_reason',
        'is_postponed', 'postponed_date', 'postponed_start_time', 'postponed_door_time', 'postponed_end_time', 'postponed_reason',
        'door_sales_only', 'is_promoted', 'promoted_until',

        // single day
        'event_date', 'start_time', 'door_time', 'end_time',

        // range
        'range_start_date', 'range_end_date', 'range_start_time', 'range_end_time',

        // multi-day json
        'multi_slots',

        // media
        'poster_url', 'hero_image_url',

        // location & links
        'venue', 'address', 'website_url', 'facebook_url', 'event_website_url',

        // content
        'short_description', 'description', 'ticket_terms',

        // seo json
        'seo',
    ];

    protected $casts = [
        // translatables
        'title'             => 'array',
        'subtitle'          => 'array',
        'short_description' => 'array',
        'description'       => 'array',
        'slug'              => 'array',
        'ticket_terms'      => 'array',

        // date-only
        'event_date'        => 'date',
        'range_start_date'  => 'date',
        'range_end_date'    => 'date',
        'postponed_date'    => 'date',
        'promoted_until'    => 'date',

        // flags
        'is_sold_out'       => 'bool',
        'is_cancelled'      => 'bool',
        'is_postponed'      => 'bool',
        'door_sales_only'   => 'bool',
        'is_promoted'       => 'bool',

        // commission
        'commission_rate'   => 'decimal:2',

        // json
        'multi_slots'       => 'array',
        'seo'               => 'array',
    ];

    /* Core Relations */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
}

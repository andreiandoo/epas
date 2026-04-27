<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Tour extends Model
{
    use Translatable;

    public array $translatable = ['description', 'short_description'];

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'artist_id',
        'name',
        'slug',
        'type',
        'status',
        'start_date',
        'end_date',
        'budget_cents',
        'currency',
        'poster_url',
        'cover_url',
        'description',
        'short_description',
        'setlist',
        'setlist_duration_minutes',
        'faq',
        'age_min',
        'marketplace_organizer_id',
        'routing_notes',
        'meta',
    ];

    protected $casts = [
        'description' => 'array',
        'short_description' => 'array',
        'setlist' => 'array',
        'faq' => 'array',
        'meta' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'budget_cents' => 'integer',
        'setlist_duration_minutes' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('event_date');
    }

    /**
     * Get budget in major currency units.
     */
    public function getBudgetAttribute(): ?float
    {
        return $this->budget_cents ? $this->budget_cents / 100 : null;
    }

    /**
     * Number of events linked to this tour.
     */
    public function getEventCountAttribute(): int
    {
        return $this->events()->count();
    }

    /**
     * Backwards-compat alias.
     */
    public function getStopCountAttribute(): int
    {
        return $this->event_count;
    }

    /**
     * Computed period [first event_date, last event_date] across linked events.
     * Falls back to the manually-set start_date / end_date columns when there
     * are no events yet.
     *
     * @return array{start: ?\Carbon\Carbon, end: ?\Carbon\Carbon}
     */
    public function getPeriodAttribute(): array
    {
        $row = \DB::table('events')
            ->where('tour_id', $this->id)
            ->selectRaw('MIN(event_date) as min_date, MAX(event_date) as max_date')
            ->first();

        $min = $row?->min_date ? \Carbon\Carbon::parse($row->min_date) : ($this->start_date ?: null);
        $max = $row?->max_date ? \Carbon\Carbon::parse($row->max_date) : ($this->end_date ?: null);

        return ['start' => $min, 'end' => $max];
    }

    /**
     * Sum of capacities across all linked events. Returns -1 if any event
     * has unlimited capacity (mirrors Event::getTotalCapacityAttribute).
     */
    public function getTotalCapacityAttribute(): int
    {
        $events = $this->events()->with('ticketTypes:id,event_id,quota_total')->get();
        if ($events->isEmpty()) return 0;

        $total = 0;
        foreach ($events as $event) {
            $cap = $event->total_capacity;
            if ($cap === -1) return -1;
            $total += $cap;
        }
        return $total;
    }

    /**
     * Total tickets sold across linked events.
     * Mirrors the per-type "Valide" count from EventResource Vânzări tab
     * (line ~3157) so the Tour aggregate matches the per-type breakdown
     * shown on /marketplace/events/{id}/edit. Includes invitations
     * (no order) and excludes externally-imported orders.
     */
    public function getTotalSoldAttribute(): int
    {
        $eventIds = $this->events()->pluck('id');
        if ($eventIds->isEmpty()) return 0;

        return (int) Ticket::whereIn('event_id', $eventIds)
            ->whereIn('status', ['valid', 'used'])
            ->where(function ($q) {
                $q->whereDoesntHave('order')
                    ->orWhereHas('order', fn ($qq) => $qq->where('source', '!=', 'external_import'));
            })
            ->count();
    }

    /**
     * Total net revenue (RON) across all linked events. Uses the same
     * formula as EventResource Vânzări tab — Net (RON) card —
     * via EventResource::getSalesBreakdown(), so the tour-level number
     * is just the sum of every event's published net.
     */
    public function getTotalNetAttribute(): float
    {
        $events = $this->events()->get();
        $total = 0.0;
        foreach ($events as $event) {
            $breakdown = \App\Filament\Marketplace\Resources\EventResource::getSalesBreakdown($event);
            $total += (float) ($breakdown['total_net'] ?? 0);
        }
        return round($total, 2);
    }

    /**
     * Distinct cities across linked events' venues.
     *
     * @return Collection<int, string>
     */
    public function getCitiesAttribute(): Collection
    {
        return $this->events()
            ->with('venue:id,city')
            ->get()
            ->pluck('venue.city')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Distinct artists across linked events (with pivot order/headliner info
     * from the first event each appears in).
     *
     * @return Collection<int, Artist>
     */
    public function getDistinctArtistsAttribute(): Collection
    {
        return $this->events()
            ->with('artists:id,name,slug,main_image_url')
            ->get()
            ->flatMap(fn ($e) => $e->artists)
            ->unique('id')
            ->values();
    }

    /**
     * Check if tour is currently running.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        $now = now()->toDateString();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    public function scopePlanning($query)
    {
        return $query->where('status', 'planning');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeForArtist($query, int $artistId)
    {
        return $query->where('artist_id', $artistId);
    }
}

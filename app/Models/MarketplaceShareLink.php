<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceShareLink extends Model
{
    protected $fillable = [
        'code',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'name',
        'event_ids',
        'is_active',
        'has_password',
        'password_hash',
        'show_participants',
        'show_revenue',
        'ticket_data',
        'participants_data',
        'ticket_data_updated_at',
        'access_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'event_ids' => 'array',
        'ticket_data' => 'array',
        'participants_data' => 'array',
        'is_active' => 'boolean',
        'has_password' => 'boolean',
        'show_participants' => 'boolean',
        'show_revenue' => 'boolean',
        'ticket_data_updated_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
    }

    /**
     * Compute fresh ticket totals (sold/total/revenue + per-ticket-type
     * breakdown) for a set of events. Both the organizer-side controller
     * and the public share view call this so the snapshot stays in sync.
     * The optional $organizerId enforces ownership when invoked from the
     * authenticated organizer endpoint.
     */
    public static function computeFreshTicketStats(array $eventIds, ?int $organizerId = null): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $validStatuses = ['paid', 'confirmed', 'completed'];
        $result = [];

        $query = Event::whereIn('id', $eventIds);
        if ($organizerId !== null) {
            $query->where('marketplace_organizer_id', $organizerId);
        }
        $events = $query->with(['ticketTypes'])->get();

        foreach ($events as $event) {
            $sold = Ticket::where('event_id', $event->id)
                ->whereHas('order', fn ($q) => $q->whereIn('status', $validStatuses))
                ->whereIn('status', ['valid', 'used'])
                ->count();
            $total = 0;
            foreach ($event->ticketTypes as $tt) {
                $q = (int) ($tt->quota_total ?? 0);
                if ($q < 0) { $total = -1; break; }
                $total += $q;
            }
            $revenue = (float) Ticket::where('event_id', $event->id)
                ->whereHas('order', fn ($q) => $q->whereIn('status', $validStatuses))
                ->whereIn('status', ['valid', 'used'])
                ->sum('price');

            $result[(string) $event->id] = [
                'sold' => $sold,
                'total' => $total,
                'revenue_net' => round($revenue, 2),
                'currency' => 'RON',
                'ticket_types' => $event->ticketTypes->map(function ($tt) use ($validStatuses) {
                    $soldTt = Ticket::where('ticket_type_id', $tt->id)
                        ->whereHas('order', fn ($q) => $q->whereIn('status', $validStatuses))
                        ->whereIn('status', ['valid', 'used'])
                        ->count();
                    $name = is_array($tt->name)
                        ? ($tt->name['ro'] ?? $tt->name['en'] ?? reset($tt->name) ?: 'Bilet')
                        : ($tt->name ?? 'Bilet');
                    return [
                        'name' => $name,
                        'sold' => $soldTt,
                        'total' => (int) ($tt->quota_total ?? 0),
                    ];
                })->values()->all(),
            ];
        }

        return $result;
    }
}

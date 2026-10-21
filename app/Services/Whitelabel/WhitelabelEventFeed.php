<?php

namespace App\Services\Whitelabel;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Performance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Event data for an organizer's own site (e.g. the Qfeel WordPress plugin).
 *
 * Read only. Gives everything we know about an event in one flat shape, plus
 * the list of dates ("occurrences") it is played on, so the site can keep its
 * own list of performances in step with Ambilet.
 */
class WhitelabelEventFeed
{
    /** Hard cap for the full list, an organizer never has this many upcoming events. */
    public const MAX_EVENTS = 300;

    /** Fields of a child event that fall back to the parent when empty. */
    protected const INHERITED = [
        'subtitle', 'short_description', 'description', 'poster_url', 'hero_image_url', 'video_url',
        'gallery', 'min_age', 'theater_director', 'theater_lead', 'theater_duration', 'theater_cast',
        'theater_creative', 'facebook_url', 'event_website_url', 'venue_id', 'marketplace_event_category_id',
    ];

    public function __construct(protected MarketplaceClient $client)
    {
    }

    /**
     * Published events of the organizer that are not over yet, cancelled ones included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(int $organizerId): array
    {
        $today = now()->toDateString();

        return $this->baseQuery($organizerId)
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('is_template', false)->orWhereNull('is_template'))
            ->where(function ($q) use ($today) {
                $q->where('event_date', '>=', $today)
                    ->orWhere('range_end_date', '>=', $today)
                    ->orWhere('postponed_date', '>=', $today)
                    ->orWhereHas('performances', fn ($p) => $p->where('starts_at', '>=', $today))
                    // Multi-day events never split into children keep their dates only in multi_slots
                    ->orWhere(fn ($md) => $md->where('duration_mode', 'multi_day')->whereRaw(
                        "EXISTS (SELECT 1 FROM jsonb_array_elements(CASE WHEN jsonb_typeof(multi_slots::jsonb) = 'array' THEN multi_slots::jsonb ELSE '[]'::jsonb END) AS slot WHERE (slot->>'date') >= ?)",
                        [$today]
                    ));
            })
            ->orderBy('id')
            ->limit(self::MAX_EVENTS)
            ->get()
            ->map(fn (Event $event) => $this->payload($event))
            ->values()
            ->all();
    }

    /**
     * One event of the organizer. Missing events and events of other
     * organizers look the same ("deleted"), so nothing leaks through here.
     */
    public function single(int $organizerId, int $eventId): array
    {
        $event = $this->baseQuery($organizerId)->whereKey($eventId)->first();

        if (!$event) {
            return ['id' => $eventId, 'status' => 'deleted', 'occurrences' => []];
        }
        if (!$event->is_published) {
            return ['id' => $event->id, 'status' => 'unpublished', 'occurrences' => []];
        }
        // Multi-day templates are only a container, each date is its own child event
        if ($event->is_template) {
            return ['id' => $event->id, 'status' => 'template', 'occurrences' => []];
        }

        return $this->payload($event);
    }

    protected function baseQuery(int $organizerId)
    {
        return Event::query()
            ->where('marketplace_client_id', $this->client->id)
            ->where('marketplace_organizer_id', $organizerId)
            ->with([
                'venue',
                'parent.venue',
                'marketplaceEventCategory',
                'artists:artists.id,artists.name',
                'ticketTypes',
                'parent.ticketTypes',
                'performances' => fn ($q) => $q->orderBy('starts_at'),
            ]);
    }

    public function payload(Event $event): array
    {
        $parent = $event->parent_id ? $event->parent : null;
        $pick = fn (string $field) => $this->filled($event->{$field}) ? $event->{$field} : $parent?->{$field};
        $text = fn (string $field) => $this->translated($event, $field) ?: ($parent ? $this->translated($parent, $field) : null);

        $venue = $event->venue ?? $parent?->venue;
        $category = $event->marketplaceEventCategory ?? $parent?->marketplaceEventCategory;
        $ticketTypes = $this->publicTicketTypes($event->ticketTypes->isNotEmpty() ? $event->ticketTypes : ($parent?->ticketTypes ?? collect()));
        $prices = $ticketTypes->map(fn ($tt) => ($tt->sale_price_cents ?: $tt->price_cents) / 100);
        $duration = $pick('theater_duration');

        return [
            'id' => $event->id,
            'status' => 'published',
            'slug' => $event->slug,
            'parent_id' => $event->parent_id,
            'updated_at' => $event->updated_at?->toIso8601String(),

            'title' => $text('title'),
            'subtitle' => $text('subtitle'),
            'short_description' => $text('short_description'),
            'description' => $text('description'),
            'category' => $category ? $this->translated($category, 'name') : null,
            'poster_url' => $this->fileUrl($pick('poster_url')),
            'hero_url' => $this->fileUrl($pick('hero_image_url')),
            'video_url' => $pick('video_url'),
            'gallery' => collect((array) $pick('gallery'))->map(fn ($img) => $this->fileUrl(is_array($img) ? ($img['path'] ?? $img['url'] ?? null) : $img))->filter()->values()->all(),
            'min_age' => $pick('min_age'),
            'duration' => $duration,
            'duration_minutes' => $this->minutes($duration),
            'director' => $pick('theater_director'),
            'lead' => $pick('theater_lead'),
            'cast' => $this->people($pick('theater_cast')),
            'creative' => $this->people($pick('theater_creative')),
            'artists' => $event->artists->pluck('name')->filter()->values()->all(),
            'facebook_url' => $pick('facebook_url'),
            'website_url' => $pick('event_website_url'),

            'venue' => $venue ? [
                'name' => $this->translated($venue, 'name'),
                'city' => $venue->city,
                'address' => $venue->address ?? $event->address,
                'latitude' => $venue->lat,
                'longitude' => $venue->lng,
                'google_maps_url' => $venue->google_maps_url,
            ] : null,

            'is_cancelled' => (bool) $event->is_cancelled,
            'cancel_reason' => $event->is_cancelled ? $event->cancel_reason : null,
            'is_postponed' => (bool) $event->is_postponed,
            'postponed_reason' => $event->is_postponed ? $event->postponed_reason : null,
            'is_sold_out' => (bool) $event->is_sold_out || ($ticketTypes->isNotEmpty() && $ticketTypes->every(fn ($tt) => $tt->available_quantity <= 0)),
            'door_sales_only' => (bool) $event->door_sales_only,
            'is_free' => $prices->isNotEmpty() && $prices->max() <= 0,
            'min_price' => $prices->isNotEmpty() ? $prices->min() : null,
            'currency' => $this->client->currency ?? 'RON',

            'occurrences' => $this->occurrences($event),
        ];
    }

    /**
     * The dates an event is played on. Each one has a stable key the site uses
     * to tell its rows apart: "e{event}" for a plain event, "p{performance}"
     * for a performance, "e{event}-s{n}" for a slot of a multi-day event.
     */
    protected function occurrences(Event $event): array
    {
        $performances = $event->performances;
        if ($performances->isNotEmpty()) {
            return $performances->map(fn (Performance $p) => [
                'key' => 'p' . $p->id,
                'performance_id' => $p->id,
                'mode' => 'single',
                'date' => $p->starts_at?->format('Y-m-d'),
                'start_time' => $p->starts_at?->format('H:i'),
                'end_time' => $p->ends_at?->format('H:i'),
                'door_time' => $p->door_time ? substr((string) $p->door_time, 0, 5) : null,
                'label' => $p->label,
                'is_premiere' => (bool) $p->is_premiere,
                'is_cancelled' => $p->status === 'cancelled',
            ])->values()->all();
        }

        if (($event->duration_mode ?? null) === 'range') {
            return [[
                'key' => 'e' . $event->id,
                'performance_id' => null,
                'mode' => 'range',
                'date' => $event->range_start_date?->format('Y-m-d'),
                'end_date' => $event->range_end_date?->format('Y-m-d'),
                'start_time' => $this->time($event->range_start_time ?? $event->start_time),
                'end_time' => $this->time($event->range_end_time ?? $event->end_time),
                'door_time' => $this->time($event->door_time),
                'label' => null,
                'is_premiere' => false,
                'is_cancelled' => false,
            ]];
        }

        // Multi-day event that was never split into child events
        if (($event->duration_mode ?? null) === 'multi_day' && !empty($event->multi_slots) && !$event->event_date) {
            return collect($event->multi_slots)->values()->map(fn ($slot, $i) => [
                'key' => 'e' . $event->id . '-s' . ($i + 1),
                'performance_id' => null,
                'mode' => 'single',
                'date' => $slot['date'] ?? null,
                'start_time' => $this->time($slot['start_time'] ?? null),
                'end_time' => $this->time($slot['end_time'] ?? null),
                'door_time' => $this->time($slot['door_time'] ?? null),
                'label' => null,
                'is_premiere' => false,
                'is_cancelled' => false,
            ])->filter(fn ($o) => $o['date'])->values()->all();
        }

        // A postponed event with a new date is played on the new date
        $postponed = $event->is_postponed && $event->postponed_date;

        return [[
            'key' => 'e' . $event->id,
            'performance_id' => null,
            'mode' => 'single',
            'date' => ($postponed ? $event->postponed_date : $event->event_date)?->format('Y-m-d'),
            'start_time' => $this->time($postponed ? ($event->postponed_start_time ?? $event->start_time) : $event->start_time),
            'end_time' => $this->time($postponed ? ($event->postponed_end_time ?? $event->end_time) : $event->end_time),
            'door_time' => $this->time($postponed ? ($event->postponed_door_time ?? $event->door_time) : $event->door_time),
            'label' => null,
            'is_premiere' => false,
            'is_cancelled' => false,
        ]];
    }

    /** Same filter the public event page uses for ticket types. */
    protected function publicTicketTypes(Collection $ticketTypes): Collection
    {
        return $ticketTypes->filter(fn ($tt) => $tt->status === 'active'
            && !$tt->is_entry_ticket
            && !($tt->meta['is_invitation'] ?? false)
            && !$tt->isTestPos()
            && !$tt->isFreeWithCode())->values();
    }

    /** Cast / creative team rows come as [{name, role}] from the event form. */
    protected function people($rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /** "2h 45min", "1h30", "90 min" -> minutes. Null when it can't be read. */
    protected function minutes(?string $duration): ?int
    {
        if (!$duration) {
            return null;
        }
        $duration = mb_strtolower($duration);
        $hours = preg_match('/(\d+)\s*(h|ore|ora|oră)/u', $duration, $h) ? (int) $h[1] : 0;
        if (preg_match('/(\d+)\s*(m|min)/u', $duration, $m)) {
            $minutes = (int) $m[1];
        } elseif ($hours && preg_match('/\d+\s*h\s*(\d{1,2})\b/u', $duration, $m)) {
            $minutes = (int) $m[1];
        } else {
            $minutes = 0;
        }
        if (!$hours && !$minutes && preg_match('/^\s*(\d+)\s*$/', $duration, $plain)) {
            $minutes = (int) $plain[1];
        }
        $total = $hours * 60 + $minutes;

        return $total > 0 ? $total : null;
    }

    protected function translated($model, string $field): ?string
    {
        $language = $this->client->language ?? 'ro';
        $value = method_exists($model, 'getTranslation')
            ? ($model->getTranslation($field, $language) ?: $model->getTranslation($field, 'ro') ?: $model->getTranslation($field, 'en'))
            : $model->{$field};
        if (is_array($value)) {
            $value = $value[$language] ?? $value['ro'] ?? reset($value) ?: null;
        }

        return $this->filled($value) ? (string) $value : null;
    }

    protected function fileUrl($path): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }

    protected function time($value): ?string
    {
        return $value ? substr((string) $value, 0, 5) : null;
    }

    protected function filled($value): bool
    {
        return !($value === null || $value === '' || $value === []);
    }
}

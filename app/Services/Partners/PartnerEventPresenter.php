<?php

namespace App\Services\Partners;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the partner-facing view of a marketplace event.
 *
 * Prices follow the public listing rules (MarketplaceEventsController::formatEvent),
 * so "from X lei" on a partner site matches the card on the marketplace site.
 * The output must be deterministic: PartnerEventFeed hashes it to detect changes.
 *
 * Expects the relations loaded by PartnerEventFeed::relations().
 */
class PartnerEventPresenter
{
    /** An event with this many tickets left, or fewer, counts as few_left. */
    private const FEW_LEFT_TICKETS = 20;

    /** ...or with this share of its capacity left. */
    private const FEW_LEFT_RATIO = 0.10;

    /**
     * @param array<int, true> $partnerArtistIds artists with a public page on the marketplace site
     */
    public function __construct(
        private readonly MarketplaceClient $client,
        private readonly array $partnerArtistIds,
    ) {
    }

    public static function forClient(MarketplaceClient $client): self
    {
        $partnerArtistIds = DB::table('marketplace_artist_partners')
            ->where('marketplace_client_id', $client->id)
            ->where('is_partner', true)
            ->pluck('artist_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return new self($client, $partnerArtistIds);
    }

    /**
     * Public site root, e.g. https://ambilet.ro (no trailing slash).
     */
    public static function siteUrl(MarketplaceClient $client): ?string
    {
        $domain = preg_replace('#^(https?:?/?/?|//)#i', '', (string) $client->domain);
        $domain = trim($domain, '/');

        if ($domain === '') {
            return null;
        }

        return (str_contains($domain, 'localhost') ? 'http' : 'https') . '://' . $domain;
    }

    public static function timezoneFor(MarketplaceClient $client): string
    {
        return MarketplaceTz::tz($client);
    }

    public function timezone(): string
    {
        return static::timezoneFor($this->client);
    }

    /**
     * Until when the event counts as upcoming: its end, or 23:59 on its last day
     * when no end time is set (as Event::getEffectiveEndDatetime does).
     */
    public function listedUntil(Event $event): ?Carbon
    {
        return $this->schedule($event)['listed_until'];
    }

    /**
     * @param array|null $previous the payload stored last time, reused for image dimensions
     * @return array|null null when the event has no usable start date
     */
    public function present(Event $event, ?array $previous = null): ?array
    {
        $schedule = $this->schedule($event);

        if ($schedule['starts_at'] === null) {
            return null;
        }

        $language = $this->client->language ?? 'ro';
        $parent = $event->parent_id ? $event->parent : null;
        $siteUrl = static::siteUrl($this->client);

        $venue = $event->venue ?? $parent?->venue;
        $category = $event->marketplaceEventCategory ?? $parent?->marketplaceEventCategory;
        // Explicit order: the hash must not change when the database returns ties differently.
        $genres = ($event->eventGenres->isNotEmpty() ? $event->eventGenres : ($parent?->eventGenres ?? collect()))
            ->sortBy('id');
        $artists = ($event->artists->isNotEmpty() ? $event->artists : ($parent?->artists ?? collect()))
            ->sortBy([
                fn ($a, $b) => (int) ($a->pivot->sort_order ?? 0) <=> (int) ($b->pivot->sort_order ?? 0),
                fn ($a, $b) => $a->id <=> $b->id,
            ]);
        $ticketTypes = $this->publicTicketTypes($event->ticketTypes);
        $usesParentTickets = $ticketTypes->isEmpty() && $parent !== null;
        if ($usesParentTickets) {
            $ticketTypes = $this->publicTicketTypes($parent->ticketTypes);
        }

        $title = $event->getTranslation('title', $language);
        $venueName = $venue?->getTranslation('name', $language);
        $marketplaceCity = ($event->marketplaceCity ?? $parent?->marketplaceCity)?->getTranslation('name', $language);

        return [
            'id' => $event->id,
            'status' => $event->is_cancelled ? 'cancelled' : ($event->is_postponed ? 'postponed' : 'published'),
            'title' => $title,
            // Same title without the city or venue an organizer typed into it.
            'title_clean' => $this->cleanTitle($title, [$marketplaceCity, $venue?->city, $venueName]),
            'url' => $siteUrl ? $siteUrl . '/bilete/' . $event->slug : null,
            'starts_at' => $schedule['starts_at']->toIso8601String(),
            'ends_at' => $schedule['ends_at']?->toIso8601String(),
            'original_starts_at' => $schedule['original_starts_at']?->toIso8601String(),
            'slots' => $schedule['slots'],
            'venue' => $venue ? [
                'id' => $venue->id,
                'name' => $venueName,
                'city' => $venue->city,
                'address' => $venue->address,
            ] : null,
            'price' => $this->price($event, $parent, $ticketTypes, $usesParentTickets),
            'availability' => $this->availability($event, $parent, $ticketTypes),
            'image' => $this->image($event->poster_url ?: $parent?->poster_url, $previous['image'] ?? null),
            'image_wide' => $this->image($event->hero_image_url ?: $parent?->hero_image_url, $previous['image_wide'] ?? null),
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
            ] : null,
            'genres' => $genres->map(fn ($genre) => $genre->getTranslation('name', $language))->values()->all(),
            'genre_slugs' => $genres->pluck('slug')->values()->all(),
            'artists' => $artists->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'url' => ($siteUrl && $artist->is_active && isset($this->partnerArtistIds[$artist->id]))
                    ? $siteUrl . '/artist/' . $artist->slug
                    : null,
                'image' => $artist->portrait_full_url ?? $artist->main_image_full_url,
                'headliner' => (bool) ($artist->pivot->is_headliner ?? false),
                'co_headliner' => (bool) ($artist->pivot->is_co_headliner ?? false),
            ])->values()->all(),
        ];
    }

    /**
     * Drops a city or venue an organizer put in the title, only where it is a
     * separate part at the start or the end: "BRAȘOV: Metal Militia, TÂMPLĂRIE"
     * becomes "Metal Militia". Diacritics and case are ignored. Returns the
     * original title when what is left is too short to be a name.
     *
     * @param array<int, string|null> $extras city and venue names to drop
     */
    private function cleanTitle(?string $title, array $extras): ?string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return null;
        }

        $clean = $title;
        foreach ($extras as $extra) {
            $extra = trim((string) $extra);
            if (mb_strlen($extra) < 3) {
                continue;
            }

            $needle = $this->looseNeedle($extra);
            $clean = (string) preg_replace('/^\s*' . $needle . '\s*[:\-–—|,]+\s*/iu', '', $clean);
            $clean = (string) preg_replace('/\s*[:\-–—|,]+\s*' . $needle . '\s*$/iu', '', $clean);
        }

        // A multibyte charlist would corrupt UTF-8, so trimming goes through a pattern.
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);
        $clean = (string) preg_replace('/^[\s\-–—|,:;.]+|[\s\-–—|,:;.]+$/u', '', $clean);

        return mb_strlen($clean) >= 3 ? $clean : $title;
    }

    /**
     * A pattern that matches the text whatever its diacritics, case or spacing.
     */
    private function looseNeedle(string $text): string
    {
        // Both the comma-below and the legacy cedilla forms, common in imported data.
        $variants = [
            'a' => 'aăâ',
            'i' => 'iî',
            's' => 'sșş',
            't' => 'tțţ',
        ];

        $pattern = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (preg_match('/\s/u', $character)) {
                $pattern .= '\s+';

                continue;
            }

            $folded = mb_strtolower(Str::ascii(mb_strtolower($character)));
            $pattern .= isset($variants[$folded])
                ? '[' . $variants[$folded] . mb_strtoupper($variants[$folded]) . ']'
                : preg_quote($character, '/');
        }

        return $pattern;
    }

    /**
     * Start, end and (multi-day) slots in the marketplace timezone. ends_at is
     * set only when an end time is known; listed_until always is. A postponed
     * event reports its new date, with the original one alongside.
     */
    private function schedule(Event $event): array
    {
        $slots = [];

        switch ($event->duration_mode) {
            case 'range':
                $lastDay = $event->range_end_date ?? $event->range_start_date;
                $start = $this->at($event->range_start_date, $event->range_start_time ?? $event->start_time);
                $end = $event->range_end_time ? $this->at($lastDay, $event->range_end_time) : null;
                break;

            case 'multi_day':
                $valid = collect($event->multi_slots ?? [])
                    ->filter(fn ($slot) => is_array($slot)
                        && !empty($slot['date'])
                        && $this->at($slot['date'], $slot['start_time'] ?? null) !== null)
                    ->sortBy(fn ($slot) => $slot['date'] . ' ' . ($slot['start_time'] ?? ''))
                    ->values();
                $slots = $valid->map(fn ($slot) => [
                    'starts_at' => $this->at($slot['date'], $slot['start_time'] ?? null)->toIso8601String(),
                    'ends_at' => $this->endAt($slot['date'], $slot['start_time'] ?? null, $slot['end_time'] ?? null)?->toIso8601String(),
                ])->all();
                $first = $valid->first();
                $last = $valid->last();
                $lastDay = $last['date'] ?? null;
                $start = $first ? $this->at($first['date'], $first['start_time'] ?? null) : null;
                $end = $last ? $this->endAt($last['date'], $last['start_time'] ?? null, $last['end_time'] ?? null) : null;
                break;

            default:
                $lastDay = $event->event_date;
                $start = $this->at($event->event_date, $event->start_time);
                $end = $this->endAt($event->event_date, $event->start_time, $event->end_time);
        }

        $original = null;
        if ($event->is_postponed && $event->postponed_date) {
            $original = $start;
            $startTime = $event->postponed_start_time ?? $event->start_time ?? $event->range_start_time;
            $lastDay = $event->postponed_date;
            $start = $this->at($event->postponed_date, $startTime);
            $end = $this->endAt($event->postponed_date, $startTime, $event->postponed_end_time);
            $slots = [];
        }

        $listedUntil = $end ?? $this->at($lastDay, '23:59');
        if ($start && (!$listedUntil || $listedUntil->lt($start))) {
            $listedUntil = $start->copy();
        }

        return [
            'starts_at' => $start,
            'ends_at' => $end,
            'listed_until' => $listedUntil,
            'original_starts_at' => $original,
            'slots' => $slots,
        ];
    }

    private function at(mixed $date, ?string $time): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
        $clock = $time ? substr($time, 0, 5) : '00:00';

        try {
            return Carbon::parse("{$day} {$clock}:00", $this->timezone());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * End of a single day; an end time before the start time means past midnight.
     */
    private function endAt(mixed $date, ?string $startTime, ?string $endTime): ?Carbon
    {
        if (empty($endTime)) {
            return null;
        }

        $end = $this->at($date, $endTime);
        $start = $this->at($date, $startTime);

        if ($end && $start && $startTime && $end->lt($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Ticket types a buyer can see: no invitations, test POS or code-only free tickets.
     * Callers load only active, non-entry types.
     */
    private function publicTicketTypes(?Collection $ticketTypes): Collection
    {
        return ($ticketTypes ?? collect())
            ->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false) && !$tt->isTestPos() && !$tt->isFreeWithCode())
            ->sortBy('id')
            ->values();
    }

    private function price(Event $event, ?Event $parent, Collection $ticketTypes, bool $usesParentTickets): array
    {
        $currency = $ticketTypes->pluck('currency')->filter()->first() ?? $this->client->currency ?? 'RON';

        if ($ticketTypes->isEmpty()) {
            return [
                'min' => $event->min_price !== null ? round((float) $event->min_price, 2) : null,
                'max' => null,
                'currency' => $currency,
            ];
        }

        $cents = $ticketTypes->map(fn ($tt) => $tt->sale_price_cents > 0 ? (int) $tt->sale_price_cents : (int) ($tt->price_cents ?? 0));

        // A child occurrence may carry per-performance prices on the parent.
        if ($usesParentTickets && $event->event_date) {
            $day = $event->event_date->format('Y-m-d');
            $time = $event->start_time ? substr($event->start_time, 0, 5) : null;
            $performance = $parent->performances->first(fn ($p) => $p->starts_at
                && $p->starts_at->format('Y-m-d') === $day
                && (!$time || $p->starts_at->format('H:i') === $time));

            if ($performance) {
                $cents = $ticketTypes->map(function ($tt) use ($performance) {
                    $override = $performance->getEffectivePrice($tt);

                    return $override ?? ($tt->sale_price_cents > 0 ? (int) $tt->sale_price_cents : (int) ($tt->price_cents ?? 0));
                });
            }
        }

        // A multi-day event may price some performances differently.
        if ($event->duration_mode === 'multi_day' && !$event->parent_id) {
            $performances = $event->performances()
                ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                ->whereNotNull('ticket_overrides')
                ->get();

            foreach ($performances as $performance) {
                foreach ($ticketTypes as $tt) {
                    $override = $performance->getEffectivePrice($tt);
                    if ($override !== null) {
                        $cents->push((int) $override);
                    }
                }
            }
        }

        $paid = $cents->filter(fn ($value) => $value > 0);
        $pool = $paid->isNotEmpty() ? $paid : $cents;

        return [
            'min' => round($pool->min() / 100, 2),
            'max' => round($pool->max() / 100, 2),
            'currency' => $currency,
        ];
    }

    private function availability(Event $event, ?Event $parent, Collection $ticketTypes): string
    {
        if ($event->is_sold_out || $parent?->is_sold_out) {
            return 'sold_out';
        }

        if ($ticketTypes->isEmpty()) {
            return 'available';
        }

        $remaining = 0;
        $capacity = 0;

        foreach ($ticketTypes as $tt) {
            if ($tt->is_sold_out ?? false) {
                continue;
            }

            if ($tt->quota_total === null || $tt->quota_total < 0) {
                return 'available';
            }

            $capacity += (int) $tt->quota_total;
            $remaining += max(0, (int) $tt->quota_total - (int) ($tt->quota_sold ?? 0));
        }

        if ($remaining <= 0) {
            return 'sold_out';
        }

        if ($remaining <= self::FEW_LEFT_TICKETS || ($capacity > 0 && $remaining / $capacity <= self::FEW_LEFT_RATIO)) {
            return 'few_left';
        }

        return 'available';
    }

    private function image(?string $path, ?array $previous): ?array
    {
        if (empty($path)) {
            return null;
        }

        $isUrl = (bool) preg_match('#^https?://#i', $path);
        $url = $isUrl ? $path : Storage::disk('public')->url($path);

        // Reading dimensions touches the file; skip it when the image is unchanged.
        if ($previous && ($previous['url'] ?? null) === $url) {
            return $previous;
        }

        $width = null;
        $height = null;

        if (!$isUrl) {
            try {
                $file = Storage::disk('public')->path($path);
                $size = is_file($file) ? @getimagesize($file) : false;
                if ($size) {
                    [$width, $height] = [(int) $size[0], (int) $size[1]];
                }
            } catch (\Throwable) {
                // Dimensions are optional.
            }
        }

        return ['url' => $url, 'width' => $width, 'height' => $height];
    }
}

<?php

namespace App\Services\Shorts;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use App\Support\PlainText;
use Illuminate\Support\Collection;

/**
 * Turns Short models into the JSON shape the mobile feed consumes
 * (docs/plans/shorts.md §5).
 *
 * Playback URLs for managed assets are signed here, per request — they are
 * short-lived and must never be cached or persisted.
 */
class ShortPayload
{
    public function __construct(private readonly ShortReminderService $reminders) {}

    /**
     * @param  Collection<int, Short>  $shorts
     * @param  array<int, int>  $likedIds
     * @param  array<int, int>  $savedIds
     * @return array<int, array<string, mixed>>
     */
    public function collection(
        Collection $shorts,
        array $likedIds = [],
        array $savedIds = [],
        ?string $feed = null,
        array $remindedIds = [],
    ): array {
        $liked = array_flip($likedIds);
        $saved = array_flip($savedIds);
        $reminded = array_flip($remindedIds);

        return $shorts
            ->map(fn (Short $short) => $this->one(
                $short,
                isset($liked[$short->id]),
                isset($saved[$short->id]),
                $feed,
                isset($reminded[$short->id]),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function one(
        Short $short,
        bool $liked = false,
        bool $saved = false,
        ?string $feed = null,
        bool $reminded = false,
    ): array {
        return [
            'id' => $short->id,
            'source' => $short->source,
            'feed' => $feed,
            'playback' => [
                'hls_url' => $short->is_external ? null : $short->playback_url,
                'poster_url' => $short->poster_url,
                // LQIP shown before the poster paints, so a slow network gets a
                // colour rather than a black hole (D9).
                'blurhash' => $short->blurhash,
            ],
            'embed_html' => $short->is_external ? $short->embed_html : null,
            'source_url' => $short->is_external ? $short->source_url : null,
            'duration' => $short->duration,
            'aspect' => $short->aspect,
            'title' => $short->title,
            'caption' => $short->caption,
            /* Descrierea proprietarului, scurtata. Feed-ul arata pana acum doar
               titlul si cateva date seci — textul exista deja in catalog si e
               deja public pe pagina evenimentului/artistului/locatiei. */
            'description' => $this->description($short),
            'hashtags' => $short->hashtags ?? [],
            'language' => $short->language,
            'music_credit' => $short->music_credit,
            'owner' => $this->owner($short),
            'event' => $this->event($short),
            'cta' => $this->cta($short),
            // Drives content warnings and the "no autoplay" opt-out (D7/D10).
            'content_flags' => $short->content_flags ?? [],
            // Subtitle tracks for <track kind="subtitles"> (B6). Only serialised
            // when the relation was loaded, so the feed never N+1s for them.
            'captions' => $short->relationLoaded('captions')
                ? $short->captions->map(fn ($caption) => [
                    'language' => $caption->language,
                    'url' => $caption->url,
                    'auto_generated' => (bool) $caption->auto_generated,
                ])->values()->all()
                : [],
            'stats' => [
                'likes' => (int) $short->likes,
                'views' => (int) $short->views,
                'shares' => (int) $short->shares,
            ],
            'viewer' => [
                'liked' => $liked,
                'saved' => $saved,
                // Without this the "remind me" button only remembers within one
                // session: the client had no way to learn a reminder was already
                // set, so reopening the app offered it again (D2).
                'reminded' => $reminded,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function owner(Short $short): ?array
    {
        // An editorial short has no owner at all. Calling owner()->first() on
        // one builds a MorphTo with an empty foreign key, which Postgres rejects
        // outright ("zero-length delimited identifier") while SQLite quietly
        // tolerates it — so the guard has to come before the relation is touched.
        if (! $short->owner_type || ! $short->owner_id) {
            return null;
        }

        // Attribute access, never owner()->first(): it resolves the morph safely
        // and reuses the eager-loaded relation when the feed loaded it.
        $owner = $short->owner;

        if (! $owner) {
            return null;
        }

        return [
            'type' => $this->morphAlias($short->owner_type),
            'id' => $owner->getKey(),
            'slug' => $owner->slug ?? null,
            'name' => PlainText::of($owner->name ?? null) ?? PlainText::of($owner->title ?? null),
            /* Eticheta de tip. Feed-ul afisa in pastila colorata NUMELE
               proprietarului, adica exact ce scria si in titlul de dedesubt —
               deci pastila nu spunea nimic. In prototip acolo sta categoria
               („Concerte", „Locatie"), iar numele sta doar in titlu. */
            'label' => $this->ownerLabel($short->owner_type),
            /* Randurile de detaliu. Un short de sala arata pana acum doar numele:
               nici oras, nici adresa, nici nota — desi toate exista in catalog. */
            'details' => $this->ownerDetails($owner),
        ];
    }

    /** Cum se numeste tipul, in limba interfetei. */
    protected function ownerLabel(?string $ownerType): ?string
    {
        return match ($this->morphAlias($ownerType)) {
            'venue' => 'Locație',
            'artist' => 'Artist',
            'event' => 'Eveniment',
            default => null,
        };
    }

    /**
     * Detaliile scurte de sub titlu, in ordinea in care se citesc.
     *
     * Numai date deja publice pe pagina proprietarului — feed-ul nu expune
     * nimic in plus fata de site.
     *
     * @return array<int, array{icon: string, text: string}>
     */
    protected function ownerDetails(object $owner): array
    {
        $rows = [];

        if ($owner instanceof Venue) {
            $address = is_string($owner->address ?? null) ? trim($owner->address) : '';
            $city = is_string($owner->city ?? null) ? trim($owner->city) : '';

            $place = array_filter([$address !== '' ? $address : null, $city !== '' ? $city : null]);

            if ($place !== []) {
                $rows[] = ['icon' => 'pin', 'text' => implode(', ', $place)];
            }

            /* Nota Google, cand a fost sincronizata. Accesorul intoarce null
               fara `google_place_id`, deci salile nelegate nu arata o nota
               inventata. */
            $rating = $owner->google_reviews_payload['rating'] ?? null;

            if (is_numeric($rating) && (float) $rating > 0) {
                $count = $owner->google_reviews_payload['review_count'] ?? null;
                $rows[] = [
                    'icon' => 'star',
                    'text' => is_numeric($count) && (int) $count > 0
                        ? sprintf('%.1f · %d recenzii', (float) $rating, (int) $count)
                        : sprintf('%.1f', (float) $rating),
                ];
            }

            if (is_numeric($owner->capacity ?? null) && (int) $owner->capacity > 0) {
                $rows[] = ['icon' => 'user', 'text' => number_format((int) $owner->capacity, 0, ',', '.').' locuri'];
            }

            return $rows;
        }

        if ($owner instanceof Event) {
            if ($owner->event_date) {
                $rows[] = ['icon' => 'cal', 'text' => $owner->event_date->format('d M Y')];
            }

            /* Sala si orasul, intr-un singur rand: pe latimea ramasa langa rail
               doua randuri separate ar rupe urat un nume lung de sala. */
            $venue = $owner->relationLoaded('venue') ? $owner->venue : null;
            $place = array_filter([
                $venue ? PlainText::of($venue->name) : null,
                $venue && is_string($venue->city ?? null) && trim($venue->city) !== '' ? trim($venue->city) : null,
            ]);

            if ($place !== []) {
                $rows[] = ['icon' => 'pin', 'text' => implode(' · ', $place)];
            }

            $organizer = $owner->relationLoaded('marketplaceOrganizer') ? $owner->marketplaceOrganizer : null;
            $organizerName = $organizer ? PlainText::of($organizer->name ?? null) : null;

            if ($organizerName) {
                $rows[] = ['icon' => 'user', 'text' => $organizerName];
            }

            return $rows;
        }

        if ($owner instanceof Artist) {
            $where = array_filter([
                is_string($owner->city ?? null) && trim($owner->city) !== '' ? trim($owner->city) : null,
                is_string($owner->country ?? null) && trim($owner->country) !== '' ? trim($owner->country) : null,
            ]);

            if ($where !== []) {
                $rows[] = ['icon' => 'pin', 'text' => implode(', ', $where)];
            }

            return $rows;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function event(Short $short): ?array
    {
        $event = $short->relationLoaded('event') ? $short->event : null;

        if (! $event) {
            return null;
        }

        return [
            'id' => $event->id,
            'slug' => $event->slug ?? null,
            'title' => PlainText::of($event->title ?? null) ?? PlainText::of($event->name ?? null),
            'date' => $event->event_date?->toDateString() ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function cta(Short $short): ?array
    {
        if ($short->cta_type === 'none' || $short->cta_type === null) {
            return null;
        }

        $cta = [
            'type' => $short->cta_type,
            'label' => $this->ctaLabel($short),
            'url' => $short->cta_url,
            'ticket_type_id' => $short->cta_ticket_type_id,
            'promo_code' => $short->promo_code,
            'on_sale_at' => null,
            'pending' => false,
        ];

        // A buy button for tickets that are not on sale yet is a dead end; the
        // client turns it into a countdown + "remind me" instead (D2).
        if ($short->cta_type === 'buy_tickets') {
            $window = $this->reminders->saleWindow($short);
            $cta['on_sale_at'] = $window['on_sale_at']?->toIso8601String();
            $cta['pending'] = $window['pending'];
        }

        return $cta;
    }

    /**
     * Eticheta butonului principal.
     *
     * Cea din baza e scrisa la GENERARE si ramane inghetata acolo. Pentru un
     * eveniment asta nu merge: pretul minim se schimba pe masura ce se vand
     * categoriile ieftine, iar un buton care promite „de la 50 lei" cand cel mai
     * ieftin bilet ramas costa 90 e o minciuna, nu o scapare de afisare. Deci
     * pentru bilete se recalculeaza la fiecare cerere; pentru restul ramane ce
     * scrie in rand.
     */
    protected function ctaLabel(Short $short): ?string
    {
        if ($short->cta_type !== 'buy_tickets') {
            return $short->cta_label;
        }

        $price = $this->minTicketPrice($short);

        if ($price === null) {
            // Fara pret cunoscut nu inventam o cifra.
            return $short->cta_label ?: 'Vezi biletele';
        }

        return 'Bilete de la '.$this->money($price).' lei';
    }

    /** Cel mai mic pret ACTIV al evenimentului, sau null cand nu se poate afla. */
    protected function minTicketPrice(Short $short): ?float
    {
        $event = $short->relationLoaded('event') ? $short->event : null;

        if (! $event) {
            return null;
        }

        try {
            $types = $event->relationLoaded('ticketTypes')
                ? $event->ticketTypes
                : $event->ticketTypes()->where('status', 'active')->get(['price', 'status']);

            $prices = $types
                ->filter(fn ($t) => $t->status === 'active' && is_numeric($t->price) && (float) $t->price > 0)
                ->map(fn ($t) => (float) $t->price);

            return $prices->isEmpty() ? null : $prices->min();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Fara zecimale cand sunt zero — „90 lei", nu „90,00 lei". */
    protected function money(float $value): string
    {
        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0, ',', '.')
            : number_format($value, 2, ',', '.');
    }

    /**
     * Descrierea proprietarului, taiata la o lungime care incape peste imagine.
     *
     * Se taie la ultimul cuvant intreg, nu la caracter: „Concert extraordi…" e
     * mai rau decat un rand mai scurt.
     */
    protected function description(Short $short): ?string
    {
        if (is_string($short->caption) && trim($short->caption) !== '') {
            // Un text scris de om bate descrierea din catalog.
            return null;
        }

        $owner = ($short->owner_type && $short->owner_id) ? $short->owner : null;

        $raw = match (true) {
            $owner instanceof Event => PlainText::of($owner->short_description) ?: PlainText::of($owner->description),
            $owner instanceof Venue => PlainText::of($owner->description),
            $owner instanceof Artist => PlainText::of($owner->bio_html),
            default => null,
        };

        return $this->excerpt($raw);
    }

    protected function excerpt(?string $html, int $max = 180): ?string
    {
        if (! is_string($html) || trim($html) === '') {
            return null;
        }

        // Descrierile din catalog sunt HTML; feed-ul afiseaza text simplu.
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > $max * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " ,.;:–-").'…';
    }

    /**
     * Short, stable type token for the client ("event", "artist", ...) instead
     * of leaking the FQCN.
     */
    protected function morphAlias(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return str(class_basename($type))->snake()->toString();
    }
}

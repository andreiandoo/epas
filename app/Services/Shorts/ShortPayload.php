<?php

namespace App\Services\Shorts;

use App\Models\Short;
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
            'name' => $owner->name ?? $owner->title ?? null,
        ];
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
            'title' => $event->title ?? $event->name ?? null,
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
            'label' => $short->cta_label,
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

<?php

namespace App\Services\Shorts;

/**
 * Opaque cursor for the infinite feed.
 *
 * The feed is ordered by (is_featured desc, published_at desc, id desc), so a
 * cursor only has to carry the last row's published_at + id. Offset pagination
 * is deliberately avoided: rows shift under the reader as shorts get published.
 *
 * Mai poarta si id-urile SERVITE recent, si acelea nu-s un moft.
 * Pe segmentele ordonate de ranker, pagina se alege dintr-un bazin de pana la
 * 200 de randuri, dar cursorul avanseaza pe cheia de RECENTA — deci un short
 * urcat in pagina 1 din adancul bazinului ramane in continuare in fata
 * cursorului si reapare peste cateva pagini. Exact asta se vedea deruland:
 * aceleasi short-uri la distanta scurta. Lista e marginita, deci cursorul nu
 * creste la nesfarsit; short-urile ies din ea dupa cateva pagini, cand oricum
 * cursorul de recenta a trecut de ele.
 */
class ShortFeedCursor
{
    /** Cate id-uri servite se retin. Peste ~40 cursorul devine incomod de lung. */
    public const MEMORY = 40;

    /**
     * @param  array<int, int>  $servedIds
     */
    public function __construct(
        public readonly ?string $publishedAt,
        public readonly int $id,
        public readonly bool $featured,
        public readonly array $servedIds = [],
    ) {}

    public static function decode(?string $cursor): ?self
    {
        if (! $cursor) {
            return null;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($raw === false) {
            return null;
        }

        $parts = json_decode($raw, true);

        if (! is_array($parts) || ! isset($parts['i'])) {
            return null;
        }

        $served = is_array($parts['s'] ?? null)
            ? array_values(array_filter(array_map('intval', $parts['s'])))
            : [];

        return new self(
            publishedAt: $parts['p'] ?? null,
            id: (int) $parts['i'],
            featured: (bool) ($parts['f'] ?? false),
            servedIds: array_slice($served, -self::MEMORY),
        );
    }

    public function encode(): string
    {
        $json = json_encode([
            'p' => $this->publishedAt,
            'i' => $this->id,
            'f' => $this->featured,
            's' => array_slice($this->servedIds, -self::MEMORY),
        ]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}

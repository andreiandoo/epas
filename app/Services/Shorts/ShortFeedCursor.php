<?php

namespace App\Services\Shorts;

/**
 * Opaque cursor for the infinite feed.
 *
 * The feed is ordered by (is_featured desc, published_at desc, id desc), so a
 * cursor only has to carry the last row's published_at + id. Offset pagination
 * is deliberately avoided: rows shift under the reader as shorts get published.
 */
class ShortFeedCursor
{
    public function __construct(
        public readonly ?string $publishedAt,
        public readonly int $id,
        public readonly bool $featured,
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

        return new self(
            publishedAt: $parts['p'] ?? null,
            id: (int) $parts['i'],
            featured: (bool) ($parts['f'] ?? false),
        );
    }

    public function encode(): string
    {
        $json = json_encode([
            'p' => $this->publishedAt,
            'i' => $this->id,
            'f' => $this->featured,
        ]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}

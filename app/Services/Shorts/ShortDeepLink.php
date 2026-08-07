<?php

namespace App\Services\Shorts;

/**
 * Deep links into the mobile app (docs/plans/shorts.md §B11).
 *
 * Kept in one place so the scheme lives in config and never gets spelled out
 * across controllers, blades and push payloads.
 */
class ShortDeepLink
{
    public function forShort(int $shortId): string
    {
        return $this->scheme().'://shorts/'.$shortId;
    }

    public function forCollection(string $slug): string
    {
        return $this->scheme().'://shorts/collection/'.$slug;
    }

    public function forEvent(int $eventId): string
    {
        return $this->scheme().'://events/'.$eventId;
    }

    protected function scheme(): string
    {
        return (string) config('shorts.deep_link.scheme', 'tixello');
    }
}

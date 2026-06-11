<?php

namespace App\Observers;

use App\Models\Seating\SeatingSection;
use App\Services\Cache\AmbiletCacheBuster;
use Illuminate\Support\Facades\DB;

/**
 * Observer that fires AmbiletCacheBuster whenever a seating section is
 * created/updated/deleted. Hooks via afterCommit() so we never bust
 * caches for a transaction that ends up rolled back.
 *
 * Lives in a separate class from any other SeatingSection observer to
 * keep concerns isolated — feature toggles for the buster can disable
 * just this observer without touching seating business logic.
 */
class SeatingSectionBustObserver
{
    public function __construct(protected AmbiletCacheBuster $buster) {}

    public function saved(SeatingSection $section): void
    {
        $this->fire($section->layout_id);
    }

    public function deleted(SeatingSection $section): void
    {
        $this->fire($section->layout_id);
    }

    protected function fire(?int $layoutId): void
    {
        if (!$layoutId) return;

        // afterCommit ensures we don't bust on a rolled-back save.
        // Falls back to immediate execution outside transactions.
        DB::afterCommit(function () use ($layoutId) {
            $this->buster->bustLayout($layoutId);
        });
    }
}

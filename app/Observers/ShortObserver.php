<?php

namespace App\Observers;

use App\Jobs\Shorts\GenerateBlurhashJob;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Services\Shorts\ShortGamificationService;
use Illuminate\Support\Facades\Log;

/**
 * Side effects of a short's own lifecycle.
 *
 * These live on the model rather than in the panel action that happens to
 * trigger them today. A short can be published from a Filament bulk action, a
 * single edit, the moderation queue or a command, and a reward wired to one of
 * those paths is a reward that silently does not exist on the other three —
 * which is exactly what happened to the UGC payout (D11).
 */
class ShortObserver
{
    public function created(Short $short): void
    {
        $this->queueBlurhash($short);
        $this->flushOwnerTypeCountsCache();
    }

    public function updated(Short $short): void
    {
        $this->rewardApprovedUgc($short);

        // Only when the picture itself changed. Checked here rather than inside
        // queueBlurhash() because wasRecentlyCreated stays true on an instance
        // for the rest of the request, so a "was this an insert?" flag inside a
        // shared helper silently lets every later update through.
        if ($short->wasChanged('poster_path')) {
            $this->queueBlurhash($short);
        }

        // Bust the admin ListShorts owner-type tab counts when the row
        // moves between buckets. Same 30s cache the page reads from.
        if ($short->wasChanged('owner_type')) {
            $this->flushOwnerTypeCountsCache();
        }
    }

    public function deleted(Short $short): void
    {
        $this->flushOwnerTypeCountsCache();
    }

    protected function flushOwnerTypeCountsCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('shorts:owner_type_counts');
    }

    /**
     * An attendee whose short gets approved is paid for it (D11, B9).
     *
     * Only on the FIRST publish: `published_at` is stamped once and never
     * cleared, so archiving and republishing cannot farm the reward.
     */
    protected function rewardApprovedUgc(Short $short): void
    {
        if (! $short->wasChanged('status') || $short->status !== Short::STATUS_PUBLISHED) {
            return;
        }

        if (! $short->is_ugc || ! $short->author_marketplace_customer_id) {
            return;
        }

        // getOriginal() is the value before this save — a short that already had
        // a publication timestamp has been through here before.
        if ($short->getOriginal('published_at')) {
            return;
        }

        $author = MarketplaceCustomer::find($short->author_marketplace_customer_id);

        if (! $author) {
            return;
        }

        try {
            app(ShortGamificationService::class)->recordApprovedUgc($author);
        } catch (\Throwable $e) {
            // A reward must never be able to fail a moderation decision.
            Log::warning('Shorts: could not award UGC approval points', [
                'short_id' => $short->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Every short with a poster gets an LQIP, not just ingested ones.
     *
     * The blurhash job was only ever dispatched from IngestShortJob, so native
     * uploads and auto-generated poster shorts — the majority of the catalogue —
     * never got one. That is precisely backwards: an external short arrives with
     * a platform thumbnail already, while a native upload is the case the
     * placeholder exists for (D9).
     */
    protected function queueBlurhash(Short $short): void
    {
        if ($short->blurhash || ! $short->poster_path) {
            return;
        }

        GenerateBlurhashJob::dispatch($short->id);
    }
}

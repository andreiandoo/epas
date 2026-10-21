<?php

namespace App\Observers;

use App\Jobs\NotifyWhitelabelSiteJob;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Performance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Queues a notification to the organizer's own site (settings.site_sync_url)
 * when one of its events, or a performance of one, changes.
 *
 * Only fields the site actually shows count, so view counters and the like
 * don't send anything. Never lets a failure here break the save.
 */
class WhitelabelSiteSyncObserver
{
    /** Waiting a bit lets several saves in a row go out as a single notification. */
    private const DELAY_SECONDS = 60;

    private const WATCHED = [
        'title', 'subtitle', 'slug', 'short_description', 'description', 'poster_url', 'hero_image_url',
        'video_url', 'gallery', 'min_age', 'theater_director', 'theater_lead', 'theater_duration',
        'theater_cast', 'theater_creative', 'facebook_url', 'event_website_url', 'venue_id', 'address',
        'marketplace_event_category_id', 'event_date', 'start_time', 'end_time', 'door_time', 'duration_mode',
        'range_start_date', 'range_end_date', 'range_start_time', 'range_end_time', 'multi_slots',
        'is_published', 'is_template', 'parent_id', 'is_cancelled', 'cancel_reason', 'is_sold_out',
        'is_postponed', 'postponed_date', 'postponed_start_time', 'postponed_door_time',
        'postponed_end_time', 'postponed_reason', 'door_sales_only', 'marketplace_organizer_id',
    ];

    public function saved(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($model instanceof Performance) {
                $this->notifyEvent($model->event_id);

                return;
            }
            if (!$model instanceof Event) {
                return;
            }
            if (!$model->wasRecentlyCreated && !array_intersect(array_keys($model->getChanges()), self::WATCHED)) {
                return;
            }

            $this->notify($model->marketplace_organizer_id, $model->id);

            // Moved to another organizer: the old one has to drop it
            if ($model->wasChanged('marketplace_organizer_id')) {
                $this->notify($model->getOriginal('marketplace_organizer_id'), $model->id);
            }

        });
    }

    public function deleted(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($model instanceof Performance) {
                $this->notifyEvent($model->event_id);
            } elseif ($model instanceof Event) {
                $this->notify($model->marketplace_organizer_id, $model->id);
            }
        });
    }

    private function notifyEvent(?int $eventId): void
    {
        if ($eventId) {
            $this->notify(Event::whereKey($eventId)->value('marketplace_organizer_id'), $eventId);
        }
    }

    private function notify($organizerId, int $eventId): void
    {
        $organizerId = (int) $organizerId;
        if (!$organizerId) {
            return;
        }
        if (NotifyWhitelabelSiteJob::siteUrl(MarketplaceOrganizer::find($organizerId))) {
            NotifyWhitelabelSiteJob::dispatch($organizerId, $eventId)->delay(now()->addSeconds(self::DELAY_SECONDS));
        }
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Whitelabel site sync notification not queued', ['error' => $e->getMessage()]);
        }
    }
}

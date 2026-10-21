<?php

namespace App\Jobs;

use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceWhitelabelKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells an organizer's own site that one of its events changed.
 *
 * The body only carries the event id; the site then reads the event with its
 * whitelabel key from /marketplace-client/whitelabel/sync/events/{id}.
 * Signed with that same key: X-Whitelabel-Signature = "sha256=" .
 * hmac_sha256("{timestamp}.{body}", key).
 *
 * Dispatched with a delay and unique per event, so ten saves in a row end up
 * as one notification. Retries after 1, 5, 15 and 60 minutes via release(),
 * a site that is down should not fill the error dashboard.
 */
class NotifyWhitelabelSiteJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const RETRY_DELAYS = [60, 300, 900, 3600];

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public function __construct(public int $organizerId, public int $eventId)
    {
    }

    public function uniqueId(): string
    {
        return $this->organizerId . ':' . $this->eventId;
    }

    public function handle(): void
    {
        $organizer = MarketplaceOrganizer::find($this->organizerId);
        $url = self::siteUrl($organizer);
        if (!$url) {
            return;
        }

        $body = json_encode([
            'event' => 'event.changed',
            'id' => $this->eventId,
            'occurred_at' => now()->toIso8601String(),
        ]);
        $timestamp = (string) time();

        try {
            $key = MarketplaceWhitelabelKey::where('marketplace_organizer_id', $organizer->id)->first();
            if (!$key || !$key->secret) {
                return;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Whitelabel-Timestamp' => $timestamp,
                    'X-Whitelabel-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $key->secret),
                    'User-Agent' => 'Tixello-Whitelabel-Sync/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->successful()) {
                return;
            }
            $error = 'HTTP ' . $response->status();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $attempt = $this->attempts();
        if ($attempt <= count(self::RETRY_DELAYS)) {
            $this->release(self::RETRY_DELAYS[$attempt - 1]);

            return;
        }

        Log::warning('Whitelabel site notification gave up', [
            'organizer_id' => $this->organizerId,
            'event_id' => $this->eventId,
            'url' => $url,
            'error' => $error,
        ]);
    }

    /** The site's notification address, or null when the organizer has none / widgets are off. */
    public static function siteUrl(?MarketplaceOrganizer $organizer): ?string
    {
        if (!$organizer || !($organizer->settings['widget_enabled'] ?? false)) {
            return null;
        }
        $url = trim((string) ($organizer->settings['site_sync_url'] ?? ''));

        return str_starts_with($url, 'https://') ? $url : null;
    }
}

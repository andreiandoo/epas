<?php

namespace App\Services\Tics;

use App\Models\Event;
use App\Models\TicsEventListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * tics.ro urmărește evenimentele de pe toate platformele de ticketing. Pentru evenimentele
 * unui marketplace (implicit AmBilet) întreabă tics pe ce alte platforme se mai vând și
 * păstrează răspunsul în tics_event_listings, ca paginile din admin să nu depindă de tics.
 *
 * Legătura e exactă: scraperul tics salvează la listările AmBilet chiar events.id din core.
 */
class TicsListingsService
{
    // Fiecare eveniment adaugă și URL-ul lui în query string; la 100 cererea trecea de limita
    // de 8 KB a serverului web. 25 rămâne în jur de 3-4 KB.
    private const BATCH = 25;

    /** Ultima eroare de la tics (pentru comanda de sincronizare). */
    public ?string $lastError = null;

    /** Câte cereri către tics au eșuat în ultimul sync(). */
    public int $failedRequests = 0;

    public function enabled(): bool
    {
        return (string) config('services.tics.key') !== '' && (string) config('services.tics.base_url') !== '';
    }

    private static ?bool $tableReady = null;

    /**
     * Slug-ul platformei în tics pentru un marketplace, sau null dacă nu e urmărit.
     * Tot null cât timp migrarea tabelului nu a rulat (lista de evenimente nu trebuie să cadă).
     */
    public function platformFor(?int $marketplaceClientId): ?string
    {
        if (self::$tableReady === null) {
            self::$tableReady = \Illuminate\Support\Facades\Schema::hasTable('tics_event_listings');
        }

        return self::$tableReady ? (config('services.tics.platforms')[(int) $marketplaceClientId] ?? null) : null;
    }

    public function appliesTo(Event $event): bool
    {
        return $this->platformFor($event->marketplace_client_id) !== null;
    }

    /**
     * Interoghează tics și rescrie snapshot-ul pentru evenimentele date.
     *
     * @param  Collection<int, Event>  $events
     * @return int câte evenimente au fost actualizate (cele pentru care tics a răspuns)
     */
    public function sync(Collection $events): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $updated = 0;
        $this->failedRequests = 0;
        $events->filter(fn (Event $e) => $this->appliesTo($e))
            ->groupBy(fn (Event $e) => $this->platformFor($e->marketplace_client_id))
            ->each(function (Collection $group, string $platform) use (&$updated) {
                foreach ($group->chunk(self::BATCH) as $chunk) {
                    $data = $this->fetch($platform, $chunk);
                    if ($data === null) {
                        $this->failedRequests++;
                        continue;
                    }
                    foreach ($chunk as $event) {
                        $this->store($event, $data[(string) $event->id] ?? null);
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<string, array|null>|null  null când tics nu răspunde corect
     */
    private function fetch(string $platform, Collection $events): ?array
    {
        $events->loadMissing('marketplaceClient');
        $urls = [];
        foreach ($events as $event) {
            if ($url = $this->publicUrl($event)) {
                $urls[$event->id] = $url;
            }
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Internal-Key' => (string) config('services.tics.key')])
                ->timeout((int) config('services.tics.timeout', 8))
                ->get(rtrim((string) config('services.tics.base_url'), '/') . '/internal/listings', [
                    'platform' => $platform,
                    'ids' => $events->pluck('id')->implode(','),
                    'urls' => $urls,
                ]);
        } catch (\Throwable $e) {
            $this->lastError = 'cerere eșuată: ' . $e->getMessage();
            Log::warning('tics listings: request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            $this->lastError = 'HTTP ' . $response->status() . ': ' . mb_substr(strip_tags($response->body()), 0, 200);
            Log::warning('tics listings: bad response', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        return $response->json('data');
    }

    private function store(Event $event, ?array $row): void
    {
        $platforms = $row['others'] ?? [];

        TicsEventListing::updateOrCreate(
            ['event_id' => $event->id],
            [
                'marketplace_client_id' => $event->marketplace_client_id,
                'found' => $row !== null,
                'tics_event_id' => $row['tics_event_id'] ?? null,
                'tics_url' => $row['public_url'] ?? null,
                'match_confidence' => isset($row['match_confidence']) ? (float) $row['match_confidence'] : null,
                'platforms' => $platforms,
                'other_active_count' => count(array_filter($platforms, fn ($p) => ($p['status'] ?? null) === 'active')),
                'synced_at' => now(),
            ]
        );
    }

    /** URL-ul public al evenimentului (fallback de potrivire în tics, pentru listări mai vechi). */
    private function publicUrl(Event $event): ?string
    {
        $domain = (string) ($event->marketplaceClient?->domain ?? '');
        $domain = ltrim(preg_replace('#^(https?:?/?/?|//)#i', '', $domain), '/');
        if ($domain === '' || ! $event->slug) {
            return null;
        }

        return 'https://' . rtrim($domain, '/') . '/bilete/' . $event->slug;
    }
}

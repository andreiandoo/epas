<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventGeneratedDocument;
use App\Models\Invoice;
use App\Models\MarketplacePayout;
use App\Models\TicketType;
use Illuminate\Support\Facades\Cache;

/**
 * Detects "missing / needs redoing" document alerts for a given event.
 *
 * Used by:
 *   - EventResource Detalii tab (section rendering the alert list)
 *   - EventResource list (badge column showing alert count per event)
 *
 * Caching: 5-min TTL as a safety net + explicit invalidation via
 * {@see flushCache()} called from model boot() hooks on the state
 * carriers (MarketplacePayout, OrganizerDocument, EventGeneratedDocument,
 * Invoice, TicketType, Event). See app/Providers/AppServiceProvider.php.
 */
class EventAlertsService
{
    /** Cache key prefix. Bump version to invalidate all cached alerts globally. */
    protected const CACHE_KEY = 'event_alerts:v1:';

    /** Cache TTL in seconds (safety net; explicit invalidation is primary). */
    protected const CACHE_TTL = 300;

    /**
     * Return the alert set for the event (cached). Each alert:
     *   [
     *     'type'        => string,   // stable slug for programmatic use
     *     'title'       => string,   // short label (badge/list heading)
     *     'message'     => string,   // 1-sentence explanation
     *     'action_url'  => ?string,  // deep-link to fix (optional)
     *   ]
     */
    public function getAlerts(Event $event): array
    {
        return Cache::remember(
            self::CACHE_KEY . $event->id,
            self::CACHE_TTL,
            fn () => $this->computeAlerts($event),
        );
    }

    /** Alert count only (for the badge column). Uses the same cache. */
    public function getAlertsCount(Event $event): int
    {
        return count($this->getAlerts($event));
    }

    /** Bust the cache for one event. Call from observers on state carriers. */
    public static function flushCache(int $eventId): void
    {
        Cache::forget(self::CACHE_KEY . $eventId);
    }

    /**
     * Compute the alert set from scratch. Order follows the user's spec:
     *   Cerere vizare → Decont → Factură → Impozit spectacole → PV distrugere
     * (No severity tiers — all alerts read equal weight to the operator.)
     */
    protected function computeAlerts(Event $event): array
    {
        $alerts = [];
        $eventId = $event->id;
        $isPast = $event->isPast();
        $baseDocUrl = "/marketplace/events/{$eventId}/edit?tab=documente";

        // ── 1. Cerere vizare bilete: MISSING when published ──
        $cerereAvizareDoc = EventGeneratedDocument::where('event_id', $eventId)
            ->whereHas('template', fn ($q) => $q->where('type', 'cerere_avizare'))
            ->latest('created_at')
            ->first();

        if ($event->is_published && !$cerereAvizareDoc) {
            $alerts[] = [
                'type' => 'cerere_avizare_missing',
                'title' => 'Cerere vizare bilete lipsă',
                'message' => 'Evenimentul e publicat dar nu s-a generat cererea de vizare.',
                'action_url' => $baseDocUrl,
            ];
        }

        // ── 2. Cerere vizare bilete: STALE (ticket types changed after generation) ──
        if ($cerereAvizareDoc) {
            $lastTicketTypeUpdate = TicketType::where('event_id', $eventId)
                ->max('updated_at');

            $docTimestamp = $cerereAvizareDoc->created_at;

            if ($lastTicketTypeUpdate && $docTimestamp
                && strtotime((string) $lastTicketTypeUpdate) > $docTimestamp->timestamp) {
                $alerts[] = [
                    'type' => 'cerere_avizare_stale',
                    'title' => 'Cerere vizare bilete trebuie refăcută',
                    'message' => 'S-au modificat tipuri de bilete după generarea cererii — regenerează.',
                    'action_url' => $baseDocUrl,
                ];
            }
        }

        // ── 3. Decont: MISSING when event ended ──
        $hasPayout = MarketplacePayout::where('event_id', $eventId)->exists();

        if ($isPast && !$hasPayout) {
            $alerts[] = [
                'type' => 'decont_missing',
                'title' => 'Decont lipsă',
                'message' => 'Evenimentul s-a încheiat dar nu s-a generat niciun decont.',
                'action_url' => '/marketplace/payouts',
            ];
        }

        // ── 4. Factură: MISSING when a decont exists but no invoice was emitted ──
        if ($hasPayout) {
            $payoutIds = MarketplacePayout::where('event_id', $eventId)->pluck('id');
            $hasInvoice = Invoice::whereIn('marketplace_payout_id', $payoutIds)->exists();

            if (!$hasInvoice) {
                $alerts[] = [
                    'type' => 'factura_missing',
                    'title' => 'Factură lipsă',
                    'message' => 'Decontul e generat dar nu s-a emis factura (organizator sau client general).',
                    'action_url' => '/marketplace/organizer-invoices',
                ];
            }
        }

        // ── 5. Impozit spectacole: MISSING when event ended ──
        if ($isPast) {
            $hasImpozit = EventGeneratedDocument::where('event_id', $eventId)
                ->whereHas('template', fn ($q) => $q->where('type', 'declaratie_impozite'))
                ->exists();

            if (!$hasImpozit) {
                $alerts[] = [
                    'type' => 'impozit_missing',
                    'title' => 'Impozit spectacole lipsă',
                    'message' => 'Evenimentul s-a încheiat dar nu s-a generat declarația de impozit.',
                    'action_url' => $baseDocUrl,
                ];
            }
        }

        // ── 6. PV distrugere bilete: MISSING when event ended ──
        if ($isPast) {
            $hasPvDistrugere = EventGeneratedDocument::where('event_id', $eventId)
                ->whereHas('template', fn ($q) => $q->where('type', 'pv_distrugere'))
                ->exists();

            if (!$hasPvDistrugere) {
                $alerts[] = [
                    'type' => 'pv_distrugere_missing',
                    'title' => 'PV distrugere bilete lipsă',
                    'message' => 'Evenimentul s-a încheiat dar nu s-a generat PV-ul de distrugere bilete.',
                    'action_url' => $baseDocUrl,
                ];
            }
        }

        return $alerts;
    }
}

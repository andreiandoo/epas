<?php

namespace App\Services\Analytics;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detects events where the marketplace loses more commission through
 * free-ticket giveaways (standalone invitations + zero-value paid
 * tickets) than it earns from paid sales at the same event. The engine
 * also buckets results by time window (all-time / current month /
 * past / upcoming) so the dashboard can surface only the current-month
 * slice while a full report page shows past-vs-upcoming breakdowns.
 *
 * "Invitation" here = any zero-value ticket in valid/used status:
 *   - standalone invitations (order_id NULL + meta.is_invitation=true)
 *   - free tickets sold through a real order (bulk_admin,
 *     marketplace_free, promo_100_percent, comp, etc.)
 *
 * "Commission earned" uses order.commission_amount summed per event.
 * "Commission lost" is a linear projection:
 *   (commission_earned / paid_tickets) × invitation_count
 */
class InvitationAbuseAnalyzer
{
    public const CACHE_TTL = 1800;

    private const EXCLUDED_SOURCES = ['test_order', 'external_import', 'legacy_import'];
    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];

    /**
     * Full analysis for a marketplace, cached 30 min. Same key both for
     * the dashboard partial slice and for the full report page — the
     * bucketing happens at the same call so no double compute.
     */
    public function analyze(int $marketplaceId, bool $forceRefresh = false): array
    {
        $key = "mp_invite_abuse_v2_{$marketplaceId}";
        if ($forceRefresh) {
            Cache::forget($key);
        }
        return Cache::remember($key, self::CACHE_TTL, fn () => $this->compute($marketplaceId));
    }

    private function compute(int $marketplaceId): array
    {
        $eventIds = Event::where('marketplace_client_id', $marketplaceId)
            ->pluck('id')
            ->toArray();

        if (empty($eventIds)) {
            return $this->emptyResult();
        }

        $paidStats = $this->paidStatsByEvent($eventIds);
        $invitationStats = $this->invitationStatsByEvent($eventIds);

        // Build the raw list of problem events (unenriched, still needs event
        // details for title/date/venue/organizer joined in below).
        $rawEvents = [];
        foreach ($eventIds as $eid) {
            $paid = $paidStats->get($eid);
            $inv = $invitationStats->get($eid);

            $paidTickets = (int) ($paid?->paid_tickets ?? 0);
            $invitations = (int) ($inv?->invitations ?? 0);

            if ($paidTickets === 0 || $invitations === 0) {
                continue;
            }

            $commissionEarned = round((float) ($paid?->commission_earned ?? 0), 2);
            $paidRevenue = round((float) ($paid?->paid_revenue ?? 0), 2);
            $avgCommissionPerTicket = $commissionEarned / $paidTickets;
            $lostCommission = round($avgCommissionPerTicket * $invitations, 2);

            // Threshold: only events where projected loss beats actual gain
            if ($lostCommission <= $commissionEarned) {
                continue;
            }

            $totalIssued = $paidTickets + $invitations;
            $invitationRatioPct = round($invitations / $totalIssued * 100, 1);
            $impact = match (true) {
                $lostCommission >= 10000 => 'high',
                $lostCommission >= 2000 => 'medium',
                default => 'low',
            };

            $rawEvents[] = [
                'event_id' => (int) $eid,
                'paid_tickets' => $paidTickets,
                'invitations' => $invitations,
                'total_issued' => $totalIssued,
                'invitation_ratio_pct' => $invitationRatioPct,
                'paid_revenue' => $paidRevenue,
                'commission_earned' => $commissionEarned,
                'lost_commission_estimate' => $lostCommission,
                'avg_commission_per_ticket' => round($avgCommissionPerTicket, 2),
                'impact' => $impact,
            ];
        }

        if (empty($rawEvents)) {
            return $this->emptyResult();
        }

        // Sort worst first (across the whole dataset)
        usort($rawEvents, fn ($a, $b) => $b['lost_commission_estimate'] <=> $a['lost_commission_estimate']);

        // Enrich with event / venue / organizer details for ALL problem events
        // (not just top 30 — the report page shows the full list, bucketed).
        $rawEvents = $this->enrichEvents($rawEvents);

        // Bucket by time window relative to today (Romania timezone).
        $tz = 'Europe/Bucharest';
        $now = Carbon::now($tz);
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $today = $now->copy()->startOfDay();

        $currentMonthEvents = [];
        $pastEvents = [];
        $upcomingEvents = [];

        foreach ($rawEvents as $row) {
            $eventDate = !empty($row['event_date']) ? Carbon::parse($row['event_date'], $tz) : null;

            // No event_date → treat as upcoming (nothing to compare against)
            if (!$eventDate) {
                $upcomingEvents[] = $row;
                continue;
            }

            // Current month bucket is inclusive
            if ($eventDate->between($monthStart, $monthEnd)) {
                $currentMonthEvents[] = $row;
            }

            // Past vs upcoming split (mutually exclusive from month bucket
            // above only in aggregation, not in the two lists — a current-
            // month event is either past-in-month or upcoming-in-month).
            if ($eventDate->lt($today)) {
                $pastEvents[] = $row;
            } else {
                $upcomingEvents[] = $row;
            }
        }

        return [
            'all_time' => [
                'events' => $rawEvents,
                'summary' => $this->summarize($rawEvents),
                'top_organizers' => $this->topOrganizersFrom($rawEvents),
            ],
            'current_month' => [
                'events' => $currentMonthEvents,
                'summary' => $this->summarize($currentMonthEvents),
                'top_organizers' => $this->topOrganizersFrom($currentMonthEvents),
                'label' => $now->locale('ro')->translatedFormat('F Y'),
            ],
            'past' => [
                'events' => $pastEvents,
                'summary' => $this->summarize($pastEvents),
                'top_organizers' => $this->topOrganizersFrom($pastEvents),
            ],
            'upcoming' => [
                'events' => $upcomingEvents,
                'summary' => $this->summarize($upcomingEvents),
                'top_organizers' => $this->topOrganizersFrom($upcomingEvents),
            ],
            'generated_at' => $now->toIso8601String(),
        ];
    }

    private function paidStatsByEvent(array $eventIds)
    {
        return DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'), $eventIds)
            ->whereIn('o.status', self::PAID_STATUSES)
            ->whereNotIn('o.source', self::EXCLUDED_SOURCES)
            ->whereIn('t.status', ['valid', 'used'])
            ->where(function ($q) {
                $q->whereNull('t.refund_status')
                  ->orWhere('t.refund_status', '<>', 'refunded');
            })
            ->where(DB::raw('COALESCE(t.price, 0)'), '>', 0)
            ->selectRaw("
                COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as event_id,
                COUNT(t.id) as paid_tickets,
                SUM(COALESCE(t.price, 0)) as paid_revenue,
                SUM(COALESCE(o.commission_amount, 0)) as commission_earned
            ")
            ->groupBy(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->get()
            ->keyBy('event_id');
    }

    private function invitationStatsByEvent(array $eventIds)
    {
        return DB::table('tickets as t')
            ->leftJoin('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->whereIn(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'), $eventIds)
            ->whereIn('t.status', ['valid', 'used'])
            ->where(function ($q) {
                $q->whereNull('t.refund_status')
                  ->orWhere('t.refund_status', '<>', 'refunded');
            })
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('t.order_id')
                        ->whereRaw("(t.meta::jsonb ->> 'is_invitation') = 'true'");
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('t.order_id')
                        ->where(DB::raw('COALESCE(t.price, 0)'), '=', 0);
                });
            })
            ->selectRaw("
                COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as event_id,
                COUNT(t.id) as invitations
            ")
            ->groupBy(DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'))
            ->get()
            ->keyBy('event_id');
    }

    private function enrichEvents(array $rows): array
    {
        if (empty($rows)) return [];
        $ids = array_column($rows, 'event_id');
        $details = Event::with(['venue:id,name,city', 'marketplaceOrganizer:id,name'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($rows as &$row) {
            $event = $details->get($row['event_id']);
            if (!$event) continue;
            $row['event_title'] = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: ('Event #' . $event->id);
            $row['event_date'] = $event->event_date?->format('Y-m-d');
            $row['is_past'] = $event->event_date && $event->event_date < now();
            $venueName = null;
            if ($event->venue) {
                $raw = $event->venue->name;
                $venueName = is_array($raw) ? ($raw['ro'] ?? $raw['en'] ?? reset($raw) ?: null) : $raw;
            }
            $row['venue_name'] = $venueName;
            $row['venue_city'] = $event->venue?->city;
            $row['organizer_id'] = $event->marketplace_organizer_id;
            $row['organizer_name'] = $event->marketplaceOrganizer?->name;
        }
        unset($row);
        return $rows;
    }

    private function summarize(array $events): array
    {
        if (empty($events)) {
            return [
                'events_count' => 0,
                'total_lost' => 0,
                'total_earned' => 0,
                'net_impact' => 0,
                'total_invitations' => 0,
                'total_paid_tickets' => 0,
                'invitation_ratio_pct' => 0,
                'unique_organizers' => 0,
            ];
        }
        $totalLost = round(array_sum(array_column($events, 'lost_commission_estimate')), 2);
        $totalEarned = round(array_sum(array_column($events, 'commission_earned')), 2);
        $totalInv = array_sum(array_column($events, 'invitations'));
        $totalPaid = array_sum(array_column($events, 'paid_tickets'));
        $organizers = array_filter(array_column($events, 'organizer_id'));
        return [
            'events_count' => count($events),
            'total_lost' => $totalLost,
            'total_earned' => $totalEarned,
            'net_impact' => round($totalLost - $totalEarned, 2),
            'total_invitations' => $totalInv,
            'total_paid_tickets' => $totalPaid,
            'invitation_ratio_pct' => ($totalInv + $totalPaid) > 0
                ? round($totalInv / ($totalInv + $totalPaid) * 100, 1)
                : 0,
            'unique_organizers' => count(array_unique($organizers)),
        ];
    }

    private function topOrganizersFrom(array $events): array
    {
        $byOrg = [];
        foreach ($events as $row) {
            $oid = $row['organizer_id'] ?? null;
            if (!$oid) continue;
            if (!isset($byOrg[$oid])) {
                $byOrg[$oid] = [
                    'organizer_id' => (int) $oid,
                    'organizer_name' => $row['organizer_name'] ?? ('Organizer #' . $oid),
                    'events' => 0,
                    'total_invitations' => 0,
                    'total_paid_tickets' => 0,
                    'total_lost' => 0.0,
                    'total_earned' => 0.0,
                ];
            }
            $byOrg[$oid]['events']++;
            $byOrg[$oid]['total_invitations'] += $row['invitations'];
            $byOrg[$oid]['total_paid_tickets'] += $row['paid_tickets'];
            $byOrg[$oid]['total_lost'] += $row['lost_commission_estimate'];
            $byOrg[$oid]['total_earned'] += $row['commission_earned'];
        }
        foreach ($byOrg as &$o) {
            $o['total_lost'] = round($o['total_lost'], 2);
            $o['total_earned'] = round($o['total_earned'], 2);
        }
        unset($o);
        usort($byOrg, fn ($a, $b) => $b['total_lost'] <=> $a['total_lost']);
        return array_slice($byOrg, 0, 10);
    }

    private function emptyResult(): array
    {
        $emptyBucket = [
            'events' => [],
            'summary' => $this->summarize([]),
            'top_organizers' => [],
        ];
        return [
            'all_time' => $emptyBucket,
            'current_month' => $emptyBucket + ['label' => Carbon::now('Europe/Bucharest')->locale('ro')->translatedFormat('F Y')],
            'past' => $emptyBucket,
            'upcoming' => $emptyBucket,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

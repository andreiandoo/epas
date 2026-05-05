<?php

namespace App\Services\OrderDisputeEvidence;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Collects everything required to defend a payment dispute / chargeback
 * for one order: who, when, from where, what they did, what they bought,
 * whether they actually used the tickets.
 */
class OrderDisputeEvidenceService
{
    /**
     * @return array{
     *   order: array<string,mixed>,
     *   customer: array<string,mixed>,
     *   sessions: array<int,array<string,mixed>>,
     *   events: array<int,array<string,mixed>>,
     *   tickets: array<int,array<string,mixed>>,
     *   summary: array<string,mixed>
     * }
     */
    public function collect(Order $order): array
    {
        $order->loadMissing(['marketplaceOrganizer', 'marketplaceClient', 'tickets']);

        $orderRow = [
            'id' => $order->id,
            'order_number' => $order->order_number ?? ('#' . $order->id),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_processor' => $order->payment_processor,
            'payment_reference' => $order->payment_reference,
            'subtotal' => (float) ($order->subtotal ?? 0),
            'discount_amount' => (float) ($order->discount_amount ?? 0),
            'total' => (float) ($order->total ?? 0),
            'currency' => $order->currency ?? 'RON',
            'created_at' => optional($order->created_at)->toDateTimeString(),
            'paid_at' => optional($order->paid_at)->toDateTimeString(),
            'cancelled_at' => optional($order->cancelled_at)->toDateTimeString(),
            'refunded_at' => optional($order->refunded_at)->toDateTimeString(),
            'marketplace_organizer' => $order->marketplaceOrganizer?->name,
            'marketplace_client' => $order->marketplaceClient?->name,
            'meta' => $order->meta ?? [],
        ];

        $customer = [
            'name' => $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
        ];

        // Pull tracking events tied to this order (most authoritative — only
        // events whose persistence carries order_id).
        $events = DB::table('core_customer_events')
            ->where('order_id', $order->id)
            ->orderBy('occurred_at')
            ->get(['event_type', 'page_url', 'occurred_at', 'visitor_id', 'session_id', 'ip_address', 'country_code', 'city', 'browser', 'os', 'device_type'])
            ->map(fn ($e) => (array) $e)
            ->all();

        // Resolve visitor_id from the events (or fall back to email-based lookup).
        $visitorId = collect($events)->pluck('visitor_id')->filter()->first();
        if (!$visitorId && $order->customer_email) {
            $visitorId = DB::table('core_customers')
                ->where('email', $order->customer_email)
                ->value('visitor_id');
        }

        // Wider event timeline: all events for the same visitor in the 60d
        // before the order — establishes a pre-purchase intent footprint.
        $widerEvents = [];
        if ($visitorId) {
            $widerEvents = DB::table('core_customer_events')
                ->where('visitor_id', $visitorId)
                ->where('occurred_at', '>=', $order->created_at?->copy()->subDays(60) ?? now()->subDays(60))
                ->where('occurred_at', '<=', $order->paid_at ?? $order->created_at ?? now())
                ->orderBy('occurred_at')
                ->limit(200)
                ->get(['event_type', 'page_url', 'occurred_at', 'session_id', 'ip_address', 'country_code', 'city', 'browser', 'os', 'device_type', 'order_id'])
                ->map(fn ($e) => (array) $e)
                ->all();
        }

        // Sessions for the same visitor.
        $sessions = [];
        if ($visitorId) {
            $sessions = DB::table('core_sessions')
                ->where('visitor_id', $visitorId)
                ->orderByDesc('started_at')
                ->limit(50)
                ->get([
                    'session_id',
                    'started_at',
                    'ended_at',
                    'pageviews',
                    'events',
                    'duration_seconds',
                    'is_bounce',
                    'converted',
                    'conversion_value',
                    'landing_page',
                    'exit_page',
                    'source',
                    'medium',
                    'campaign',
                    'referrer',
                    'country_code',
                    'city',
                    'device_type',
                    'browser',
                    'os',
                ])
                ->map(fn ($s) => (array) $s)
                ->all();
        }

        // Tickets + check-in proof.
        $tickets = $order->tickets->map(fn ($t) => [
            'code' => $t->code,
            'barcode' => $t->barcode,
            'status' => $t->status,
            'seat_label' => $t->seat_label,
            'price' => (float) ($t->price ?? 0),
            'attendee_name' => $t->attendee_name,
            'attendee_email' => $t->attendee_email,
            'is_cancelled' => (bool) $t->is_cancelled,
            'checked_in_at' => optional($t->checked_in_at)->toDateTimeString(),
            'checked_in_by' => $t->checked_in_by,
        ])->all();

        // Headline summary numbers for the PDF cover page.
        $summary = [
            'visitor_id' => $visitorId,
            'visit_count' => count($sessions),
            'event_count' => count($widerEvents) ?: count($events),
            'first_seen_at' => collect($widerEvents)->pluck('occurred_at')->first()
                ?? collect($events)->pluck('occurred_at')->first(),
            'tickets_total' => count($tickets),
            'tickets_checked_in' => collect($tickets)->whereNotNull('checked_in_at')->count(),
            'days_browsing_before_purchase' => $this->daysBetween(
                collect($widerEvents)->pluck('occurred_at')->first(),
                $order->paid_at ?? $order->created_at
            ),
        ];

        return [
            'order' => $orderRow,
            'customer' => $customer,
            'sessions' => $sessions,
            'events' => !empty($widerEvents) ? $widerEvents : $events,
            'tickets' => $tickets,
            'summary' => $summary,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    protected function daysBetween($a, $b): ?float
    {
        if (!$a || !$b) return null;
        try {
            $start = $a instanceof \DateTimeInterface ? $a : \Illuminate\Support\Carbon::parse($a);
            $end = $b instanceof \DateTimeInterface ? $b : \Illuminate\Support\Carbon::parse($b);
            return round($start->diffInHours($end) / 24, 2);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

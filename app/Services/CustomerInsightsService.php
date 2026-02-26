<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Gamification\CustomerBadge;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\PointsTransaction;
use App\Models\Gamification\RewardRedemption;
use App\Models\MarketplaceCustomer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerInsightsService
{
    protected int $customerId;
    protected string $email;
    protected string $orderColumn;
    protected ?Carbon $createdAt;
    protected ?Customer $coreCustomer;

    private function __construct(
        int $customerId,
        string $email,
        string $orderColumn,
        ?Carbon $createdAt,
        ?Customer $coreCustomer = null
    ) {
        $this->customerId = $customerId;
        $this->email = $email;
        $this->orderColumn = $orderColumn;
        $this->createdAt = $createdAt;
        $this->coreCustomer = $coreCustomer;
    }

    public static function forCustomer(Customer $customer): static
    {
        return new static(
            $customer->id,
            $customer->email,
            'customer_id',
            $customer->created_at,
            $customer
        );
    }

    public static function forMarketplaceCustomer(MarketplaceCustomer $customer): static
    {
        return new static(
            $customer->id,
            $customer->email,
            'marketplace_customer_id',
            $customer->created_at,
            null
        );
    }

    // ─── Lifetime Stats ───────────────────────────────────────────

    public function lifetimeStats(): array
    {
        $orders = DB::table('orders')
            ->where($this->orderColumn, $this->customerId)
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total_cents), 0) as total_value')
            ->first();

        $tickets = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->count();

        $events = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->distinct('e.id')
            ->count('e.id');

        $lifetimeDays = $this->createdAt ? (int) $this->createdAt->diffInDays(now()) : 0;

        return [
            'lifetime_value' => ($orders->total_value ?? 0) / 100,
            'lifetime_days' => $lifetimeDays,
            'customer_since' => $this->createdAt?->format('d.m.Y'),
            'total_orders' => $orders->total_orders ?? 0,
            'total_tickets' => $tickets,
            'total_events' => $events,
        ];
    }

    // ─── Order Status Breakdown ─────────────────────────────────

    public function orderStatusBreakdown(): array
    {
        $rows = DB::table('orders')
            ->where($this->orderColumn, $this->customerId)
            ->select(
                'status',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(total_cents), 0) as total_cents')
            )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $totalOrders = $rows->sum('cnt');
        $totalTickets = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->count();

        $paidValue = ($rows->get('paid')?->total_cents ?? 0)
            + ($rows->get('confirmed')?->total_cents ?? 0)
            + ($rows->get('completed')?->total_cents ?? 0);

        $refundTotal = DB::table('orders')
            ->where($this->orderColumn, $this->customerId)
            ->where('status', 'refunded')
            ->sum(DB::raw('COALESCE(refund_amount, total_cents / 100)'));

        return [
            'pending_value' => ($rows->get('pending')?->total_cents ?? 0) / 100,
            'cancelled_value' => ($rows->get('cancelled')?->total_cents ?? 0) / 100,
            'failed_value' => (($rows->get('failed')?->total_cents ?? 0) + ($rows->get('expired')?->total_cents ?? 0)) / 100,
            'refund_value' => $refundTotal ?: (($rows->get('refunded')?->total_cents ?? 0) / 100),
            'avg_per_order' => $totalOrders > 0 ? round($paidValue / $totalOrders / 100, 2) : 0,
            'avg_per_ticket' => $totalTickets > 0 ? round($paidValue / $totalTickets / 100, 2) : 0,
        ];
    }

    // ─── Monthly Orders (current year, all 12 months) ────────────

    public function monthlyOrdersCurrentYear(): array
    {
        $year = date('Y');
        $monthNames = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $rows = DB::table('orders')
            ->selectRaw("MONTH(created_at) as m, COUNT(*) as cnt, COALESCE(SUM(total_cents), 0) as total")
            ->where($this->orderColumn, $this->customerId)
            ->whereYear('created_at', $year)
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('cnt', 'm')
            ->toArray();

        $revenueRows = DB::table('orders')
            ->selectRaw("MONTH(created_at) as m, COALESCE(SUM(total_cents), 0) as total")
            ->where($this->orderColumn, $this->customerId)
            ->whereYear('created_at', $year)
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('total', 'm')
            ->toArray();

        $labels = [];
        $counts = [];
        $revenues = [];
        for ($i = 1; $i <= 12; $i++) {
            $labels[] = $monthNames[$i - 1];
            $counts[] = $rows[$i] ?? 0;
            $revenues[] = round(($revenueRows[$i] ?? 0) / 100, 2);
        }

        return [
            'year' => $year,
            'labels' => $labels,
            'counts' => $counts,
            'revenues' => $revenues,
        ];
    }

    // ─── Price Range ──────────────────────────────────────────────

    public function priceRange(): array
    {
        $prices = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('t.price')
            ->where('t.price', '>', 0)
            ->pluck('t.price')
            ->sort()
            ->values();

        if ($prices->isEmpty()) {
            return ['min' => 0, 'max' => 0, 'avg' => 0, 'median' => 0];
        }

        $count = $prices->count();
        $median = $count % 2 === 0
            ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
            : $prices[intdiv($count, 2)];

        return [
            'min' => round($prices->min(), 2),
            'max' => round($prices->max(), 2),
            'avg' => round($prices->avg(), 2),
            'median' => round($median, 2),
        ];
    }

    // ─── Venue Types ──────────────────────────────────────────────

    public function venueTypes(): array
    {
        $rows = DB::table('venue_types as vt')
            ->join('venue_type_venue as vtv', 'vtv.venue_type_id', '=', 'vt.id')
            ->join('venues as v', 'v.id', '=', 'vtv.venue_id')
            ->join('events as e', 'e.venue_id', '=', 'v.id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(vt.name, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(vt.name, '$.ro')), vt.name) as label"),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Artist Genres ────────────────────────────────────────────

    public function artistGenres(): array
    {
        $rows = DB::table('artist_genres as ag')
            ->join('artist_artist_genre as aag', 'aag.artist_genre_id', '=', 'ag.id')
            ->join('artists as a', 'a.id', '=', 'aag.artist_id')
            ->join('event_artist as ea', 'ea.artist_id', '=', 'a.id')
            ->join('events as e', 'e.id', '=', 'ea.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ag.name, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(ag.name, '$.ro')), ag.name) as label"),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Event Types ──────────────────────────────────────────────

    public function eventTypes(): array
    {
        $rows = DB::table('event_types as et')
            ->join('event_event_type as eet', 'eet.event_type_id', '=', 'et.id')
            ->join('events as e', 'e.id', '=', 'eet.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(et.name, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(et.name, '$.ro')), et.name) as label"),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Event Genres ─────────────────────────────────────────────

    public function eventGenres(): array
    {
        $rows = DB::table('event_genres as eg')
            ->join('event_event_genre as eeg', 'eeg.event_genre_id', '=', 'eg.id')
            ->join('events as e', 'e.id', '=', 'eeg.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(eg.name, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(eg.name, '$.ro')), eg.name) as label"),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Event Tags ───────────────────────────────────────────────

    public function eventTags(): array
    {
        $rows = DB::table('event_tags as etag')
            ->join('event_event_tag as eet', 'eet.event_tag_id', '=', 'etag.id')
            ->join('events as e', 'e.id', '=', 'eet.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select('etag.name as label', DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('etag.name')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Preferred Days of Week (Top 3) ───────────────────────────

    public function preferredDays(): array
    {
        $dayNames = [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică'];

        $rows = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('e.event_date')
            ->select(DB::raw('DAYOFWEEK(e.event_date) as dow'), DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('dow')
            ->orderByDesc('cnt')
            ->limit(3)
            ->get()
            ->map(function ($row) use ($dayNames) {
                // MySQL DAYOFWEEK: 1=Sunday, 2=Monday...7=Saturday → convert
                $mapped = match ((int) $row->dow) {
                    1 => 7, // Sunday
                    2 => 1, // Monday
                    3 => 2,
                    4 => 3,
                    5 => 4,
                    6 => 5,
                    7 => 6, // Saturday
                    default => (int) $row->dow,
                };
                $row->label = $dayNames[$mapped] ?? "Day {$row->dow}";
                return $row;
            });

        return $this->formatWithPercentage($rows);
    }

    // ─── Preferred Cities ─────────────────────────────────────────

    public function preferredCities(): array
    {
        $rows = DB::table('venues as v')
            ->join('events as e', 'e.venue_id', '=', 'v.id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('v.city')
            ->where('v.city', '!=', '')
            ->select('v.city as label', DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('v.city')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Preferred Start Time ─────────────────────────────────────

    public function preferredStartTimes(): array
    {
        $rows = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('e.start_time')
            ->select(DB::raw("CONCAT(LPAD(HOUR(e.start_time), 2, '0'), ':00') as label"), DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Preferred Month ──────────────────────────────────────────

    public function preferredMonths(): array
    {
        $monthNames = [
            1 => 'Ianuarie', 2 => 'Februarie', 3 => 'Martie', 4 => 'Aprilie',
            5 => 'Mai', 6 => 'Iunie', 7 => 'Iulie', 8 => 'August',
            9 => 'Septembrie', 10 => 'Octombrie', 11 => 'Noiembrie', 12 => 'Decembrie',
        ];

        $rows = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('e.event_date')
            ->select(DB::raw('MONTH(e.event_date) as m'), DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('m')
            ->orderByDesc('cnt')
            ->get()
            ->map(function ($row) use ($monthNames) {
                $row->label = $monthNames[(int) $row->m] ?? "Luna {$row->m}";
                return $row;
            });

        return $this->formatWithPercentage($rows);
    }

    // ─── Preferred Period of Month ────────────────────────────────

    public function preferredMonthPeriods(): array
    {
        $rows = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('e.event_date')
            ->select(
                DB::raw("CASE
                    WHEN DAY(e.event_date) <= 10 THEN 'Început de lună (1-10)'
                    WHEN DAY(e.event_date) <= 20 THEN 'Mijloc de lună (11-20)'
                    ELSE 'Sfârșit de lună (21-31)'
                END as label"),
                DB::raw('COUNT(DISTINCT e.id) as cnt')
            )
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->get();

        return $this->formatWithPercentage($rows);
    }

    // ─── Orders List ──────────────────────────────────────────────

    public function ordersList(int $limit = 50): array
    {
        return DB::table('orders as o')
            ->leftJoin('events as e', 'e.id', '=', 'o.event_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                'o.id',
                'o.order_number',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.ro')), 'N/A') as event_title"),
                'o.total_cents',
                'o.currency',
                'o.status',
                'o.created_at'
            )
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── Tickets List ─────────────────────────────────────────────

    public function ticketsList(int $limit = 50): array
    {
        return DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->leftJoin('events as e', 'e.id', '=', 'tt.event_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select(
                't.id',
                't.code',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.ro')), 'N/A') as event_title"),
                'tt.name as ticket_type_name',
                't.attendee_name',
                't.attendee_email',
                't.status',
                't.seat_label',
                't.price',
                't.checked_in_at'
            )
            ->orderByDesc('t.created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── Attendees / Beneficiaries ────────────────────────────────

    public function attendees(): array
    {
        return DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->whereNotNull('t.attendee_name')
            ->where('t.attendee_name', '!=', '')
            ->select('t.attendee_name', 't.attendee_email')
            ->distinct()
            ->orderBy('t.attendee_name')
            ->get()
            ->toArray();
    }

    // ─── Email Logs ───────────────────────────────────────────────

    public function emailLogs(int $limit = 50): array
    {
        return DB::table('email_logs as el')
            ->leftJoin('email_templates as et', 'et.id', '=', 'el.email_template_id')
            ->where('el.recipient_email', $this->email)
            ->select(
                'el.id',
                'el.subject',
                'el.status',
                'el.sent_at',
                'el.failed_at',
                'el.created_at',
                DB::raw("et.name as template_name")
            )
            ->orderByDesc('el.created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── Gamification Data ────────────────────────────────────────

    public function gamificationData(): array
    {
        // Gamification only works for core customers with tenant context
        if (!$this->coreCustomer) {
            return [
                'points' => null,
                'experience' => null,
                'transactions' => collect(),
                'badges' => collect(),
                'redemptions' => collect(),
            ];
        }

        $tenantId = $this->coreCustomer->primary_tenant_id ?? $this->coreCustomer->tenant_id;
        $coreId = $this->coreCustomer->id;

        $points = $tenantId
            ? CustomerPoints::where('tenant_id', $tenantId)->where('customer_id', $coreId)->first()
            : null;

        $experience = $tenantId
            ? CustomerExperience::where('tenant_id', $tenantId)->where('customer_id', $coreId)->first()
            : null;

        $transactions = PointsTransaction::where('customer_id', $coreId)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $badges = CustomerBadge::with('badge')
            ->where('customer_id', $coreId)
            ->orderByDesc('earned_at')
            ->get();

        $redemptions = RewardRedemption::with('reward')
            ->where('customer_id', $coreId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return [
            'points' => $points,
            'experience' => $experience,
            'transactions' => $transactions,
            'badges' => $badges,
            'redemptions' => $redemptions,
        ];
    }

    // ─── Monthly Orders ───────────────────────────────────────────

    public function monthlyOrders(): array
    {
        return DB::table('orders')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt, SUM(total_cents) as total")
            ->where($this->orderColumn, $this->customerId)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    // ─── Recent Events ───────────────────────────────────────────

    public function recentEvents(int $limit = 20): array
    {
        return DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select('e.id', DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.ro'))) as title"))
            ->distinct()
            ->orderByDesc('e.id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── Top Artists ──────────────────────────────────────────────

    public function topArtists(int $limit = 20): array
    {
        return DB::table('artists as a')
            ->join('event_artist as ea', 'ea.artist_id', '=', 'a.id')
            ->join('events as e', 'e.id', '=', 'ea.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select('a.id', 'a.name', DB::raw('COUNT(DISTINCT e.id) as cnt'))
            ->groupBy('a.id', 'a.name')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── Tenants List ─────────────────────────────────────────────

    public function tenantsList(): array
    {
        return DB::table('tenants as tn')
            ->join('orders as o', 'o.tenant_id', '=', 'tn.id')
            ->where('o.' . $this->orderColumn, $this->customerId)
            ->select('tn.id', 'tn.name', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(o.total_cents) as total'))
            ->groupBy('tn.id', 'tn.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    // ─── Helper: Format with Percentage ───────────────────────────

    protected function formatWithPercentage($rows): array
    {
        $total = collect($rows)->sum('cnt');

        if ($total === 0) {
            return [];
        }

        return collect($rows)->map(function ($row) use ($total) {
            return [
                'label' => $row->label,
                'count' => (int) $row->cnt,
                'percentage' => round(($row->cnt / $total) * 100, 1),
            ];
        })->toArray();
    }
}

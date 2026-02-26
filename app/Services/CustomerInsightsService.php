<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Gamification\CustomerBadge;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\PointsTransaction;
use App\Models\Gamification\RewardRedemption;
use App\Models\MarketplaceCustomer;
use App\Models\Platform\CoreCustomer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerInsightsService
{
    protected int $customerId;
    protected string $email;
    protected string $orderColumn;
    protected ?Carbon $createdAt;
    protected ?Customer $coreCustomer;
    protected ?int $marketplaceCustomerId;
    protected ?int $marketplaceClientId;
    protected ?MarketplaceCustomer $marketplaceCustomer = null;

    private function __construct(
        int $customerId,
        string $email,
        string $orderColumn,
        ?Carbon $createdAt,
        ?Customer $coreCustomer = null,
        ?int $marketplaceCustomerId = null,
        ?int $marketplaceClientId = null
    ) {
        $this->customerId = $customerId;
        $this->email = $email;
        $this->orderColumn = $orderColumn;
        $this->createdAt = $createdAt;
        $this->coreCustomer = $coreCustomer;
        $this->marketplaceCustomerId = $marketplaceCustomerId;
        $this->marketplaceClientId = $marketplaceClientId;
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
        $instance = new static(
            $customer->id,
            $customer->email,
            'marketplace_customer_id',
            $customer->created_at,
            null,
            $customer->id,
            $customer->marketplace_client_id
        );
        $instance->marketplaceCustomer = $customer;
        return $instance;
    }

    // ─── Lifetime Stats ───────────────────────────────────────────

    public function lifetimeStats(): array
    {
        $orders = DB::table('orders')
            ->where($this->orderColumn, $this->customerId)
            ->selectRaw("COUNT(*) as total_orders, SUM(" . $this->totalExpr() . ") as total_value")
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
                DB::raw("SUM(" . $this->totalExpr() . ") as total_cents")
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
            ->sum(DB::raw("COALESCE(refund_amount, " . $this->totalExpr() . " / 100)"));

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
            ->selectRaw("MONTH(created_at) as m, COUNT(*) as cnt, SUM(" . $this->totalExpr() . ") as total")
            ->where($this->orderColumn, $this->customerId)
            ->whereYear('created_at', $year)
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('cnt', 'm')
            ->toArray();

        $revenueRows = DB::table('orders')
            ->selectRaw("MONTH(created_at) as m, SUM(" . $this->totalExpr() . ") as total")
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
                DB::raw($this->totalExpr('o') . ' as total_cents'),
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
                't.checked_in_at',
                'o.payment_processor'
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
        $empty = [
            'points' => null,
            'experience' => null,
            'transactions' => collect(),
            'badges' => collect(),
            'redemptions' => collect(),
        ];

        // Core customer path
        if ($this->coreCustomer) {
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

            return compact('points', 'experience', 'transactions', 'badges', 'redemptions');
        }

        // Marketplace customer path
        if ($this->marketplaceCustomerId && $this->marketplaceClientId) {
            $points = CustomerPoints::where('marketplace_client_id', $this->marketplaceClientId)
                ->where('marketplace_customer_id', $this->marketplaceCustomerId)
                ->first();

            $experience = CustomerExperience::where('marketplace_customer_id', $this->marketplaceCustomerId)
                ->first();

            $transactions = PointsTransaction::where('marketplace_customer_id', $this->marketplaceCustomerId)
                ->when($this->marketplaceClientId, fn ($q) => $q->where('marketplace_client_id', $this->marketplaceClientId))
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $badges = CustomerBadge::with('badge')
                ->where('marketplace_customer_id', $this->marketplaceCustomerId)
                ->orderByDesc('earned_at')
                ->get();

            $redemptions = RewardRedemption::with('reward')
                ->where('marketplace_customer_id', $this->marketplaceCustomerId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return compact('points', 'experience', 'transactions', 'badges', 'redemptions');
        }

        return $empty;
    }

    // ─── Monthly Orders ───────────────────────────────────────────

    public function monthlyOrders(): array
    {
        return DB::table('orders')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt, SUM(" . $this->totalExpr() . ") as total")
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
            ->select('tn.id', 'tn.name', DB::raw('COUNT(*) as cnt'), DB::raw("SUM(" . $this->totalExpr('o') . ") as total"))
            ->groupBy('tn.id', 'tn.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    // ─── CoreCustomer Tracking Data ─────────────────────────────────

    public function trackingData(): array
    {
        $hash = hash('sha256', strtolower(trim($this->email)));
        $cc = CoreCustomer::where('email_hash', $hash)->first();

        if (! $cc) {
            return [];
        }

        return [
            'core_customer_id' => $cc->id,
            'uuid' => $cc->uuid,
            'segment' => $cc->customer_segment,
            'rfm_segment' => $cc->rfm_segment,
            'health_score' => $cc->health_score,
            'engagement_score' => $cc->engagement_score,
            // Attribution - Google
            'first_gclid' => $cc->first_gclid,
            'last_gclid' => $cc->last_gclid,
            'google_user_id' => $cc->google_user_id,
            // Attribution - Meta
            'first_fbclid' => $cc->first_fbclid,
            'last_fbclid' => $cc->last_fbclid,
            'facebook_user_id' => $cc->facebook_user_id,
            // Attribution - TikTok
            'first_ttclid' => $cc->first_ttclid,
            'last_ttclid' => $cc->last_ttclid,
            // Attribution - LinkedIn
            'first_li_fat_id' => $cc->first_li_fat_id,
            'last_li_fat_id' => $cc->last_li_fat_id,
            // UTM
            'first_utm_source' => $cc->first_utm_source,
            'first_utm_medium' => $cc->first_utm_medium,
            'first_utm_campaign' => $cc->first_utm_campaign,
            'last_utm_source' => $cc->last_utm_source,
            'last_utm_medium' => $cc->last_utm_medium,
            'last_utm_campaign' => $cc->last_utm_campaign,
            // Activity
            'first_seen_at' => $cc->first_seen_at?->format('d.m.Y H:i'),
            'last_seen_at' => $cc->last_seen_at?->format('d.m.Y H:i'),
            'total_visits' => $cc->total_visits,
            'total_pageviews' => $cc->total_pageviews,
            'total_sessions' => $cc->total_sessions,
            // Device
            'primary_device' => $cc->primary_device,
            'primary_browser' => $cc->primary_browser,
            // Email engagement
            'emails_sent' => $cc->emails_sent,
            'emails_opened' => $cc->emails_opened,
            'emails_clicked' => $cc->emails_clicked,
            'email_open_rate' => $cc->email_open_rate,
            'email_click_rate' => $cc->email_click_rate,
            // External IDs
            'stripe_customer_id' => $cc->stripe_customer_id,
            'visitor_id' => $cc->visitor_id,
        ];
    }

    // ─── Helper: total expression (handles both total_cents and total columns) ─

    protected function totalExpr(string $prefix = ''): string
    {
        $p = $prefix ? "{$prefix}." : '';

        return "COALESCE(NULLIF({$p}total_cents, 0), ROUND({$p}total * 100), 0)";
    }

    // ─── Favorites Profile (marketplace only) ──────────────────────

    public function favoritesProfile(): array
    {
        if (! $this->marketplaceCustomer) {
            return ['event_types' => [], 'event_genres' => [], 'artist_genres' => [], 'venue_types' => [], 'cities' => []];
        }

        $mc = $this->marketplaceCustomer;

        // Artist genres from favorite artists
        $artistGenreCounts = [];
        foreach ($mc->favoriteArtists()->with('artistGenres')->get() as $artist) {
            foreach ($artist->artistGenres as $genre) {
                $label = $this->extractTranslatableName($genre->name);
                $artistGenreCounts[$label] = ($artistGenreCounts[$label] ?? 0) + 1;
            }
        }

        // Venue types + cities from favorite venues
        $venueTypeCounts = [];
        $cityCounts = [];
        foreach ($mc->favoriteVenues()->with('venueTypes')->get() as $venue) {
            foreach ($venue->venueTypes as $vt) {
                $label = $this->extractTranslatableName($vt->name);
                $venueTypeCounts[$label] = ($venueTypeCounts[$label] ?? 0) + 1;
            }
            if ($venue->city) {
                $cityCounts[$venue->city] = ($cityCounts[$venue->city] ?? 0) + 1;
            }
        }

        // Event types, genres, cities from watchlist events
        $eventTypeCounts = [];
        $eventGenreCounts = [];
        foreach ($mc->watchlistEvents()->with(['eventTypes', 'eventGenres', 'venue'])->get() as $event) {
            foreach ($event->eventTypes as $et) {
                $label = $this->extractTranslatableName($et->name);
                $eventTypeCounts[$label] = ($eventTypeCounts[$label] ?? 0) + 1;
            }
            foreach ($event->eventGenres as $eg) {
                $label = $this->extractTranslatableName($eg->name);
                $eventGenreCounts[$label] = ($eventGenreCounts[$label] ?? 0) + 1;
            }
            if ($event->venue && $event->venue->city) {
                $cityCounts[$event->venue->city] = ($cityCounts[$event->venue->city] ?? 0) + 1;
            }
        }

        return [
            'event_types' => $this->sortedCounts($eventTypeCounts),
            'event_genres' => $this->sortedCounts($eventGenreCounts),
            'artist_genres' => $this->sortedCounts($artistGenreCounts),
            'venue_types' => $this->sortedCounts($venueTypeCounts),
            'cities' => $this->sortedCounts($cityCounts),
        ];
    }

    // ─── Weighted Profile (orders × 0.7 + favorites × 0.3) ──────

    public function weightedProfile(float $orderWeight = 0.7, float $favWeight = 0.3): array
    {
        $favs = $this->favoritesProfile();

        return [
            'event_types' => $this->mergeWeighted($this->eventTypes(), $favs['event_types'], $orderWeight, $favWeight),
            'event_genres' => $this->mergeWeighted($this->eventGenres(), $favs['event_genres'], $orderWeight, $favWeight),
            'artist_genres' => $this->mergeWeighted($this->artistGenres(), $favs['artist_genres'], $orderWeight, $favWeight),
            'venue_types' => $this->mergeWeighted($this->venueTypes(), $favs['venue_types'], $orderWeight, $favWeight),
            'cities' => $this->mergeWeighted($this->preferredCities(), $favs['cities'], $orderWeight, $favWeight),
        ];
    }

    // ─── Profile Narrative Generation ───────────────────────────

    public function generateProfileNarrative(): string
    {
        $stats = $this->lifetimeStats();
        $priceRange = $this->priceRange();
        $breakdown = $this->orderStatusBreakdown();
        $attendees = $this->attendees();

        // Determine data source: weighted (marketplace) or orders-only
        $isMarketplace = $this->marketplaceCustomer !== null;
        if ($isMarketplace) {
            $wp = $this->weightedProfile();
            $topEventType = $this->extractTopLabel($wp['event_types'], 'label');
            $topEventGenres = $this->extractTopLabels($wp['event_genres'], 2, 'label');
            $topVenueTypes = $this->extractTopLabels($wp['venue_types'], 2, 'label');
            $topArtistGenres = $this->extractTopLabels($wp['artist_genres'], 3, 'label');
            $topCity = $this->extractTopLabel($wp['cities'], 'label');
        } else {
            $topEventType = $this->extractTopLabel($this->eventTypes(), 'label');
            $topEventGenres = $this->extractTopLabels($this->eventGenres(), 2, 'label');
            $topVenueTypes = $this->extractTopLabels($this->venueTypes(), 2, 'label');
            $topArtistGenres = $this->extractTopLabels($this->artistGenres(), 3, 'label');
            $topCity = $this->extractTopLabel($this->preferredCities(), 'label');
        }

        $topArtistNames = collect($this->topArtists(3))->pluck('name')->implode(', ');
        $topDay = $this->extractTopLabel($this->preferredDays(), 'label');
        $topMonthPeriod = $this->extractTopLabel($this->preferredMonthPeriods(), 'label');
        $topStartTime = $this->extractTopLabel($this->preferredStartTimes(), 'label');
        $topMonths = $this->extractTopLabels($this->preferredMonths(), 2, 'label');

        // Customer demographics
        $mc = $this->marketplaceCustomer;
        $coreCustomer = $this->coreCustomer;

        $name = null;
        $gender = null;
        $city = null;
        $age = null;

        if ($mc) {
            $name = trim(($mc->first_name ?? '') . ' ' . ($mc->last_name ?? ''));
            $gender = $mc->gender;
            $city = $mc->city;
            $age = $mc->birth_date ? $mc->birth_date->age : null;
        } elseif ($coreCustomer) {
            $name = trim(($coreCustomer->first_name ?? '') . ' ' . ($coreCustomer->last_name ?? ''));
            $city = $coreCustomer->city;
            $age = $coreCustomer->date_of_birth ? $coreCustomer->date_of_birth->age : null;
            // Try linked marketplace profile for gender
            $linkedMp = MarketplaceCustomer::where('email', $this->email)->first();
            if ($linkedMp) {
                $gender = $linkedMp->gender;
                if (! $age && $linkedMp->birth_date) {
                    $age = $linkedMp->birth_date->age;
                }
                if (! $city) {
                    $city = $linkedMp->city;
                }
            }
        }

        $name = $name ?: 'Clientul';
        $isFemale = $gender === 'female';
        $genderLabel = match ($gender) {
            'male' => 'un bărbat',
            'female' => 'o femeie',
            default => null,
        };
        $interesat = $isFemale ? 'interesată' : 'interesat';

        // Check if we have enough data
        $hasOrders = ($stats['total_orders'] ?? 0) > 0;
        $hasFavorites = $isMarketplace && $this->hasFavoritesData();
        if (! $hasOrders && ! $hasFavorites) {
            return 'Nu există suficiente date pentru a genera un profil detaliat.';
        }

        // Build narrative parts
        $parts = [];

        // Opening: "{Name} este {gender} din {city}, cu vârsta de {age} ani"
        $opening = $name;
        if ($genderLabel) {
            $opening .= " este {$genderLabel}";
        }
        if ($city) {
            $opening .= ($genderLabel ? ' din ' : ' este din ') . $city;
        }
        if ($age) {
            $opening .= ", cu vârsta de {$age} de ani";
        }

        // Interests
        if ($topEventType) {
            $opening .= ", {$interesat} de {$topEventType}";
            if ($topEventGenres) {
                $opening .= " în genul {$topEventGenres}";
            }
        }
        $opening .= '.';
        $parts[] = $opening;

        // Venue types + artist genres
        $venueArtist = '';
        if ($topVenueTypes) {
            $venueArtist .= "Îi place să petreacă timp în {$topVenueTypes}";
        }
        if ($topArtistGenres) {
            $genrePart = "să asculte {$topArtistGenres}";
            if ($topArtistNames) {
                $genrePart .= " ({$topArtistNames})";
            }
            $venueArtist .= ($venueArtist ? ' și ' : '') . $genrePart;
        }
        if ($venueArtist) {
            $parts[] = $venueArtist . '.';
        }

        // Ideal event
        $idealParts = [];
        if ($topDay) {
            $idealParts[] = "într-o zi de {$topDay}";
        }
        if ($topMonthPeriod) {
            $idealParts[] = strtolower($topMonthPeriod);
        }
        if ($topCity) {
            $idealParts[] = "în orașul {$topCity}";
        }
        if ($topStartTime) {
            $idealParts[] = "la ora {$topStartTime}";
        }
        if (! empty($idealParts)) {
            $parts[] = 'Evenimentul perfect ar trebui să fie ' . implode(', ', $idealParts) . '.';
        }

        // Preferred months
        if ($topMonths) {
            $parts[] = "Lunile preferate sunt {$topMonths}.";
        }

        // Price range
        if (($priceRange['avg'] ?? 0) > 0) {
            $avg = $priceRange['avg'];
            $low = round($avg * 0.85, 2);
            $high = round($avg * 1.15, 2);
            $parts[] = "Valoarea dispusă de cheltuit pe un bilet este " . number_format($low, 2) . " - " . number_format($high, 2) . " RON";
            if (($breakdown['avg_per_order'] ?? 0) > 0) {
                $parts[count($parts) - 1] .= " și a unei comenzi " . number_format($breakdown['avg_per_order'], 2) . " RON.";
            } else {
                $parts[count($parts) - 1] .= '.';
            }
        }

        // Lifetime value + tenure
        if ($hasOrders) {
            $ltv = number_format($stats['lifetime_value'], 2);
            $days = $stats['lifetime_days'];
            $parts[] = "Lifetime Value: {$ltv} RON, client de {$days} zile.";
        }

        // Beneficiaries
        if (! empty($attendees)) {
            $attendeeNames = collect($attendees)->pluck('attendee_name')->unique()->take(5)->implode(', ');
            $parts[] = "Îi place să meargă la evenimente împreună cu {$attendeeNames}.";

            // Generate mini-profiles for beneficiaries who are also customers
            $miniProfiles = [];
            foreach (collect($attendees)->unique('attendee_email')->take(5) as $att) {
                if (! $att->attendee_email) {
                    continue;
                }
                $mini = $this->generateMiniProfile($att->attendee_email, $att->attendee_name);
                if ($mini) {
                    $miniProfiles[] = $mini;
                }
            }
            if (! empty($miniProfiles)) {
                $parts[] = implode(' ', $miniProfiles);
            }
        }

        return implode(' ', $parts);
    }

    // ─── Helper: Has Favorites Data ─────────────────────────────

    protected function hasFavoritesData(): bool
    {
        if (! $this->marketplaceCustomer) {
            return false;
        }
        $mc = $this->marketplaceCustomer;
        return $mc->favoriteArtists()->count() > 0
            || $mc->favoriteVenues()->count() > 0
            || $mc->watchlistEvents()->count() > 0;
    }

    // ─── Helper: Extract Translatable Name ──────────────────────

    protected function extractTranslatableName($name): string
    {
        if (is_array($name)) {
            return $name['ro'] ?? $name['en'] ?? reset($name) ?: '?';
        }
        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                return $decoded['ro'] ?? $decoded['en'] ?? reset($decoded) ?: '?';
            }
            return $name;
        }
        return (string) $name;
    }

    // ─── Helper: Sort Counts → [{label, count, percentage}] ────

    protected function sortedCounts(array $counts): array
    {
        if (empty($counts)) {
            return [];
        }
        arsort($counts);
        $total = array_sum($counts);
        $result = [];
        foreach ($counts as $label => $count) {
            $result[] = [
                'label' => $label,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }
        return $result;
    }

    // ─── Helper: Merge Weighted ─────────────────────────────────

    protected function mergeWeighted(array $orderItems, array $favItems, float $ow, float $fw): array
    {
        // Normalize to percentage maps
        $orderMap = [];
        foreach ($orderItems as $item) {
            $orderMap[$item['label']] = ['pct' => $item['percentage'] ?? 0, 'count' => $item['count'] ?? 0];
        }
        $favMap = [];
        foreach ($favItems as $item) {
            $favMap[$item['label']] = ['pct' => $item['percentage'] ?? 0, 'count' => $item['count'] ?? 0];
        }

        // All unique labels
        $allLabels = array_unique(array_merge(array_keys($orderMap), array_keys($favMap)));
        $merged = [];
        foreach ($allLabels as $label) {
            $oPct = $orderMap[$label]['pct'] ?? 0;
            $fPct = $favMap[$label]['pct'] ?? 0;
            $weighted = round(($oPct * $ow) + ($fPct * $fw), 1);
            $merged[] = [
                'label' => $label,
                'order_pct' => $oPct,
                'fav_pct' => $fPct,
                'weighted' => $weighted,
                'order_count' => $orderMap[$label]['count'] ?? 0,
                'fav_count' => $favMap[$label]['count'] ?? 0,
            ];
        }

        // Sort by weighted descending
        usort($merged, fn ($a, $b) => $b['weighted'] <=> $a['weighted']);

        return $merged;
    }

    // ─── Helper: Extract Top Label ──────────────────────────────

    protected function extractTopLabel(array $items, string $key = 'label'): ?string
    {
        return ! empty($items) ? ($items[0][$key] ?? null) : null;
    }

    protected function extractTopLabels(array $items, int $count = 2, string $key = 'label'): ?string
    {
        $labels = collect($items)->take($count)->pluck($key)->filter()->values();
        return $labels->isNotEmpty() ? $labels->implode(', ') : null;
    }

    // ─── Helper: Generate Mini Profile for Beneficiary ──────────

    protected function generateMiniProfile(string $email, string $name): ?string
    {
        // Try to find as MarketplaceCustomer
        $mp = MarketplaceCustomer::where('email', $email)->first();
        if ($mp) {
            $parts = [$name . ':'];
            if ($mp->gender) {
                $parts[] = match ($mp->gender) { 'male' => 'bărbat', 'female' => 'femeie', default => '' };
            }
            if ($mp->birth_date) {
                $parts[] = $mp->birth_date->age . ' ani';
            }
            if ($mp->city) {
                $parts[] = $mp->city;
            }
            return count($parts) > 1 ? '[' . implode(', ', array_filter($parts)) . ']' : null;
        }

        // Try as Customer (admin-side)
        $customer = Customer::where('email', $email)->first();
        if ($customer) {
            $parts = [$name . ':'];
            if ($customer->date_of_birth) {
                $parts[] = $customer->date_of_birth->age . ' ani';
            }
            if ($customer->city) {
                $parts[] = $customer->city;
            }
            return count($parts) > 1 ? '[' . implode(', ', array_filter($parts)) . ']' : null;
        }

        return null;
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

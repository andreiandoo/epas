<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\MarketplaceOrganizer;
use App\Models\Ticket;
use App\Services\Activities\BookingDescriber;
use App\Services\Activities\ProductAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The operator's bookings (activities module): sales, the day's arrivals per
 * product and time, CSV export, no-show, and a sales summary.
 *
 * Money per booking: total_cents is what the products cost (addons
 * included); commission_cents is the marketplace commission. When the
 * commission is added on top of the prices the operator keeps the whole
 * total, otherwise it keeps total − commission.
 */
class BookingsController extends BaseController
{
    use ResolvesOrganizer;

    private const SOLD = [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED, ActivityBooking::STATUS_CHECKED_IN, ActivityBooking::STATUS_NO_SHOW];

    /**
     * GET organizer/activities-module/bookings?from=&to=&location_id=&product_id=&status=&q=&page=
     * Sales (a package is one line with its components inside), by visit date.
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        [$from, $to] = $this->range($request, 0, 30);

        $query = $this->bookings($organizer, $request)
            ->whereNull('package_booking_id')
            ->whereDate('booking_date', '>=', $from)
            ->whereDate('booking_date', '<=', $to)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', array_merge(self::SOLD, [ActivityBooking::STATUS_CANCELLED])))
            ->when(trim((string) $request->query('q', '')), function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(confirmation_code) LIKE ?', [$like])
                    ->orWhereHas('order', fn ($o) => $o->whereRaw('LOWER(customer_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(customer_email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(order_number) LIKE ?', [$like])));
            })
            ->with(['activity.location', 'variant', 'order', 'componentBookings.activity', 'componentBookings.variant', 'tickets'])
            ->orderBy('booking_date')->orderBy('slot_start_time')->orderBy('id');

        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', 50))));

        return $this->success([
            'from'       => $from,
            'to'         => $to,
            'bookings'   => collect($page->items())->map(fn ($b) => $this->row($b))->values(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * GET organizer/activities-module/bookings/day?date=&location_id=
     * Who comes on a date: per product, per start time, with seats left.
     */
    public function day(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $date = $this->date($request->query('date')) ?? CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfDay();
        $d = $date->toDateString();

        $visits = $this->bookings($organizer, $request)
            ->whereIn('status', self::SOLD)
            ->whereDate('booking_date', '<=', $d)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $d)->orWhere(fn ($q2) => $q2->whereNull('end_date')->whereDate('booking_date', $d)))
            ->whereHas('activity', fn ($q) => $q->where('product_type', '<>', Activity::TYPE_PACKAGE))
            ->with(['activity.location', 'activity.schedules', 'activity.scheduleExceptions', 'variant', 'order', 'packageBooking.activity', 'tickets'])
            ->orderBy('slot_start_time')->orderBy('id')
            ->get();

        $availability = new ProductAvailability();
        $products = $visits->groupBy('activity_id')->map(function ($rows) use ($availability, $date) {
            $product = $rows->first()->activity;
            $groups = $rows->groupBy(fn ($b) => $b->getRawOriginal('slot_start_time') ? substr(ProductAvailability::time($b->getRawOriginal('slot_start_time')), 0, 5) : 'toată ziua');
            $capacity = $product->isDayMode() ? $availability->day($product, $date) : null;
            $slots = $product->isDayMode() ? [] : collect($availability->slots($product, $date))->keyBy(fn ($s) => substr($s['start_time'], 0, 5));

            return [
                'product_id' => $product->id,
                'title'      => BookingDescriber::booking($rows->first())['title'],
                'type'       => $product->product_type,
                'location'   => $product->location ? ($product->location->name['ro'] ?? $product->location->slug) : null,
                'mode'       => $product->booking_mode,
                'persons'    => (int) $rows->sum('quantity'),
                'checked_in' => (int) $rows->where('status', ActivityBooking::STATUS_CHECKED_IN)->sum('quantity'),
                'capacity'   => $capacity ? ['total' => $capacity['capacity'], 'left' => $capacity['remaining']] : null,
                'groups'     => $groups->map(fn ($g, $time) => [
                    'time'     => $time,
                    'persons'  => (int) $g->sum('quantity'),
                    'left'     => $slots[$time]['capacity_remaining'] ?? null,
                    'bookings' => $g->map(fn ($b) => $this->row($b))->values(),
                ])->values(),
            ];
        })->values();

        return $this->success([
            'date'     => $d,
            'persons'  => (int) $visits->sum('quantity'),
            'products' => $products,
        ]);
    }

    /** GET organizer/activities-module/bookings/export?from=&to=&location_id= (CSV) */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $organizer = $this->organizer($request);
        [$from, $to] = $this->range($request, 0, 30, 366);

        $rows = $this->bookings($organizer, $request)
            ->whereIn('status', array_merge(self::SOLD, [ActivityBooking::STATUS_CANCELLED]))
            ->whereHas('activity', fn ($q) => $q->where('product_type', '<>', Activity::TYPE_PACKAGE))
            ->whereDate('booking_date', '>=', $from)
            ->whereDate('booking_date', '<=', $to)
            ->with(['activity', 'variant', 'order', 'packageBooking.activity'])
            ->orderBy('booking_date')->orderBy('slot_start_time')->orderBy('id')
            ->get();

        $cell = fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v;
        $filename = "rezervari-{$from}-{$to}.csv";

        return response()->streamDownload(function () use ($rows, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Până la', 'Ora', 'Produs', 'Bilet', 'Cantitate', 'Pachet', 'Client', 'Telefon', 'E-mail', 'Cod rezervare', 'Comandă', 'Status', 'Valoare (lei)', 'Nr. înmatriculare', 'Suplimente'], ';');
            foreach ($rows as $b) {
                $d = BookingDescriber::booking($b);
                fputcsv($out, array_map($cell, [
                    $d['date'], $d['end_date'] ?? '', $d['start_time'] ?? '', $d['title'], $d['variant'], $d['quantity'],
                    $b->packageBooking ? BookingDescriber::booking($b->packageBooking)['title'] : '',
                    $b->order?->customer_name ?? '', $b->order?->customer_phone ?? '', $b->order?->customer_email ?? '',
                    $b->confirmation_code, $b->order?->order_number ?? '', $b->status,
                    number_format($b->total_cents / 100, 2, ',', ''), $d['vehicle_plate'] ?? '',
                    implode(', ', array_map(fn ($a) => $a['name'] . ' × ' . $a['qty'], $d['addons'])),
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** POST organizer/activities-module/bookings/{id}/no-show */
    public function noShow(Request $request, int $id): JsonResponse
    {
        $organizer = $this->organizer($request);
        $booking = $this->bookings($organizer, $request)->whereKey($id)->first();
        if (!$booking) {
            return $this->error('Rezervarea nu există', 404);
        }
        if (!in_array($booking->status, [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED], true)) {
            return $this->error('Doar o rezervare plătită și nevalidată poate fi marcată așa.', 409);
        }
        if ($booking->booking_date->toDateString() > now(ProductAvailability::TIMEZONE)->toDateString()) {
            return $this->error('Rezervarea e pentru o zi care n-a venit încă.', 409);
        }
        $booking->update(['status' => ActivityBooking::STATUS_NO_SHOW]);

        return $this->success(['booking' => $this->row($booking->fresh(['activity.location', 'variant', 'order', 'tickets']))], 'Marcată: nu s-a prezentat.');
    }

    /**
     * GET organizer/activities-module/summary?from=&to=&location_id=
     * Sales by purchase date: bookings, persons, value, commission, what the
     * operator keeps; per product and per day; next 7 days' arrivals.
     */
    public function summary(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        [$from, $to] = $this->range($request, -29, 0, 366);

        $sales = $this->bookings($organizer, $request)
            ->whereNull('package_booking_id')
            ->whereIn('status', self::SOLD)
            ->whereHas('order', fn ($q) => $q->whereRaw('DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?', [$from, $to]))
            ->with(['activity', 'order'])
            ->get();

        $mode = $organizer->getEffectiveCommissionMode();
        $keeps = function (ActivityBooking $b) use ($mode) {
            $m = $b->meta['commission_mode'] ?? $mode;
            return $m === 'added_on_top' ? $b->total_cents : $b->total_cents - $b->commission_cents;
        };

        $today = now(ProductAvailability::TIMEZONE)->toDateString();
        $next7 = now(ProductAvailability::TIMEZONE)->addDays(6)->toDateString();
        $upcoming = $this->bookings($organizer, $request)
            ->whereIn('status', [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED])
            ->whereHas('activity', fn ($q) => $q->where('product_type', '<>', Activity::TYPE_PACKAGE))
            ->whereDate('booking_date', '>=', $today)
            ->whereDate('booking_date', '<=', $next7)
            ->get(['booking_date', 'quantity']);

        return $this->success([
            'from'       => $from,
            'to'         => $to,
            'totals'     => [
                'bookings'   => $sales->count(),
                'persons'    => (int) $sales->sum('quantity'),
                'value'      => round($sales->sum('total_cents') / 100, 2),
                'commission' => round($sales->sum('commission_cents') / 100, 2),
                'net'        => round($sales->sum($keeps) / 100, 2),
            ],
            'by_product' => $sales->groupBy('activity_id')->map(fn ($rows) => [
                'product_id' => $rows->first()->activity_id,
                'title'      => BookingDescriber::booking($rows->first())['title'],
                'bookings'   => $rows->count(),
                'persons'    => (int) $rows->sum('quantity'),
                'value'      => round($rows->sum('total_cents') / 100, 2),
                'net'        => round($rows->sum($keeps) / 100, 2),
            ])->sortByDesc('value')->values(),
            'by_day'     => $sales->groupBy(fn ($b) => ($b->order->paid_at ?? $b->order->created_at)->timezone(ProductAvailability::TIMEZONE)->toDateString())
                ->map(fn ($rows, $day) => ['date' => $day, 'bookings' => $rows->count(), 'value' => round($rows->sum('total_cents') / 100, 2), 'net' => round($rows->sum($keeps) / 100, 2)])
                ->sortKeys()->values(),
            'arrivals_7_days' => [
                'today'   => (int) $upcoming->filter(fn ($b) => $b->booking_date->toDateString() === $today)->sum('quantity'),
                'persons' => (int) $upcoming->sum('quantity'),
            ],
            // What the panel and the balance page need on top of the period: the whole history, the split between
            // the site and the desk (the desk commission is the one invoiced monthly), the tickets and the catalogue.
            // Each one is guarded: a figure that cannot be read must never cost the report its own numbers.
            'all_time'   => rescue(fn () => $this->periodTotals($organizer, $request, null, null), null),
            'by_source'  => rescue(fn () => [
                'period'   => $this->sourceTotals($organizer, $request, $from, $to),
                'all_time' => $this->sourceTotals($organizer, $request, null, null),
            ], null),
            'tickets'    => rescue(fn () => $this->ticketCounts($organizer, $request), null),
            'catalogue'  => rescue(fn () => $this->catalogue($organizer), null),
            'by_month'   => rescue(fn () => $this->byMonth($organizer, $request), null),
        ]);
    }

    /** Sold bookings of this operator, optionally bought between two dates. */
    private function sold(MarketplaceOrganizer $organizer, Request $request, ?string $from, ?string $to): Builder
    {
        return $this->bookings($organizer, $request)
            ->whereNull('activity_bookings.package_booking_id')
            ->whereIn('activity_bookings.status', self::SOLD)
            ->when($from && $to, fn ($q) => $q->whereHas(
                'order',
                fn ($o) => $o->whereRaw('DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?', [$from, $to])
            ));
    }

    /**
     * Bookings, persons, value, commission and what the operator keeps. Read with one aggregate query, so "since the
     * beginning" costs the same as a week; the commission mode is the account's (a booking of its own carries the
     * same one).
     */
    private function periodTotals(MarketplaceOrganizer $organizer, Request $request, ?string $from, ?string $to): array
    {
        $row = $this->sold($organizer, $request, $from, $to)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(activity_bookings.quantity), 0) AS persons, COALESCE(SUM(activity_bookings.total_cents), 0) AS value, COALESCE(SUM(activity_bookings.commission_cents), 0) AS commission')
            ->first();

        $value = (int) ($row->value ?? 0);
        $commission = (int) ($row->commission ?? 0);
        $keeps = $organizer->getEffectiveCommissionMode() === 'added_on_top' ? $value : $value - $commission;

        return [
            'bookings'   => (int) ($row->n ?? 0),
            'persons'    => (int) ($row->persons ?? 0),
            'value'      => round($value / 100, 2),
            'commission' => round($commission / 100, 2),
            'net'        => round($keeps / 100, 2),
        ];
    }

    /** The same numbers, split between what was sold on the site and what was sold at the desk. */
    private function sourceTotals(MarketplaceOrganizer $organizer, Request $request, ?string $from, ?string $to): array
    {
        $rows = $this->sold($organizer, $request, $from, $to)
            ->join('orders', 'orders.id', '=', 'activity_bookings.order_id')
            ->selectRaw("CASE WHEN orders.source = 'pos' THEN 'pos' ELSE 'online' END AS src")
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(activity_bookings.quantity), 0) AS persons, COALESCE(SUM(activity_bookings.total_cents), 0) AS value, COALESCE(SUM(activity_bookings.commission_cents), 0) AS commission')
            ->groupBy('src')
            ->get();

        $onTop = $organizer->getEffectiveCommissionMode() === 'added_on_top';
        $out = [];
        foreach (['online', 'pos'] as $src) {
            $row = $rows->firstWhere('src', $src);
            $value = (int) ($row->value ?? 0);
            $commission = (int) ($row->commission ?? 0);
            $out[$src] = [
                'bookings'   => (int) ($row->n ?? 0),
                'persons'    => (int) ($row->persons ?? 0),
                'value'      => round($value / 100, 2),
                'commission' => round($commission / 100, 2),
                'net'        => round(($onTop ? $value : $value - $commission) / 100, 2),
            ];
        }

        return $out;
    }

    /** How many tickets this operator has out there, by state. */
    private function ticketCounts(MarketplaceOrganizer $organizer, Request $request): array
    {
        $rows = Ticket::query()
            ->whereIn('activity_booking_id', $this->sold($organizer, $request, null, null)->select('activity_bookings.id'))
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'valid' => (int) ($rows['valid'] ?? 0),
            'used'  => (int) ($rows['used'] ?? 0),
            'total' => (int) $rows->sum(),
        ];
    }

    /**
     * The last thirteen months, split between the site and the desk: what the balance page needs to show what came in
     * and what commission was charged (the desk's is the one invoiced). Grouped here rather than in SQL, so the same
     * code runs on SQLite and PostgreSQL.
     */
    private function byMonth(MarketplaceOrganizer $organizer, Request $request): array
    {
        $since = CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfMonth()->subMonths(12)->toDateString();
        $rows = $this->sold($organizer, $request, null, null)
            ->join('orders', 'orders.id', '=', 'activity_bookings.order_id')
            ->whereRaw('DATE(COALESCE(orders.paid_at, orders.created_at)) >= ?', [$since])
            ->get([
                'activity_bookings.total_cents',
                'activity_bookings.commission_cents',
                'orders.source',
                'orders.paid_at',
                'orders.created_at as order_created_at',
            ]);

        $onTop = $organizer->getEffectiveCommissionMode() === 'added_on_top';
        $months = [];
        foreach ($rows as $row) {
            $when = $row->paid_at ?: $row->order_created_at;
            $month = CarbonImmutable::parse($when)->timezone(ProductAvailability::TIMEZONE)->format('Y-m');
            $src = ($row->source ?? '') === 'pos' ? 'pos' : 'online';
            $months[$month] ??= [
                'month' => $month,
                'online' => ['value' => 0, 'commission' => 0],
                'pos' => ['value' => 0, 'commission' => 0],
            ];
            $months[$month][$src]['value'] += (int) $row->total_cents;
            $months[$month][$src]['commission'] += (int) $row->commission_cents;
        }
        krsort($months);

        return array_values(array_map(function (array $m) use ($onTop) {
            foreach (['online', 'pos'] as $src) {
                $value = $m[$src]['value'];
                $commission = $m[$src]['commission'];
                $m[$src] = [
                    'value'      => round($value / 100, 2),
                    'commission' => round($commission / 100, 2),
                    'net'        => round(($onTop ? $value : $value - $commission) / 100, 2),
                ];
            }

            return $m;
        }, $months));
    }

    /** The catalogue behind the numbers: how many products are on sale, waiting or still drafts, and their views. */
    private function catalogue(MarketplaceOrganizer $organizer): array
    {
        $rows = Activity::query()
            ->where('marketplace_organizer_id', $organizer->id)
            ->selectRaw('COUNT(*) AS n')
            // "on sale" is what a visitor can buy: published, approved and not kept for the desk only
            // booleans are compared as booleans: PostgreSQL refuses `is_published = 1`
            ->selectRaw('SUM(CASE WHEN is_published AND NOT pos_only AND (review_status IS NULL OR review_status = ?) THEN 1 ELSE 0 END) AS live', ['approved'])
            ->selectRaw('SUM(CASE WHEN review_status = ? THEN 1 ELSE 0 END) AS pending', ['pending'])
            ->selectRaw('SUM(CASE WHEN pos_only THEN 1 ELSE 0 END) AS desk_only')
            ->selectRaw('COALESCE(SUM(views_count), 0) AS views')
            ->first();

        return [
            'products' => (int) ($rows->n ?? 0),
            'live'     => (int) ($rows->live ?? 0),
            'pending'  => (int) ($rows->pending ?? 0),
            'desk_only' => (int) ($rows->desk_only ?? 0),
            'views'    => (int) ($rows->views ?? 0),
        ];
    }

    // ------------------------------------------------------------------

    /** Bookings of this operator's products (optionally one location / product). */
    private function bookings(MarketplaceOrganizer $organizer, Request $request): Builder
    {
        // columns are qualified: the summary joins orders onto this query
        return ActivityBooking::query()
            ->where('activity_bookings.marketplace_client_id', $organizer->marketplace_client_id)
            ->whereHas('activity', fn ($q) => $q->where('marketplace_organizer_id', $organizer->id))
            ->when($request->query('location_id'), fn ($q, $id) => $q->whereHas('activity', fn ($a) => $a->where('location_id', (int) $id)))
            ->when($request->query('product_id'), fn ($q, $id) => $q->where('activity_bookings.activity_id', (int) $id));
    }

    private function row(ActivityBooking $b): array
    {
        $d = BookingDescriber::booking($b);
        $tickets = collect($d['tickets'] ?? []);

        return [
            'id'                => $b->id,
            'confirmation_code' => $b->confirmation_code,
            'status'            => $b->status,
            'product_id'        => $b->activity_id,
            'type'              => $d['type'],
            'title'             => $d['title'],
            'variant'           => $d['variant'],
            'quantity'          => $d['quantity'],
            'date'              => $d['date'],
            'end_date'          => $d['end_date'],
            'start_time'        => $d['start_time'],
            'end_time'          => $d['end_time'],
            'date_label'        => $d['date_label'],
            'time_label'        => $d['time_label'],
            'location'          => $d['location']['name'] ?? null,
            'package'           => $b->packageBooking ? BookingDescriber::booking($b->packageBooking)['title'] : null,
            'components'        => array_map(fn ($c) => array_diff_key($c, ['tickets' => 1]), $d['components']),
            'addons'            => $d['addons'],
            'vehicle_plate'     => $d['vehicle_plate'],
            'value'             => $d['total'],
            'customer'          => [
                'name'  => $b->order?->customer_name,
                'email' => $b->order?->customer_email,
                'phone' => $b->order?->customer_phone,
            ],
            'order_number'      => $b->order?->order_number,
            'attendees'         => $tickets->pluck('attendee_name')->filter()->values(),
            'checked_in'        => $tickets->whereNotNull('checked_in_at')->count(),
            'tickets'           => $tickets->count(),
            'notes'             => $b->status === ActivityBooking::STATUS_PAID ? null : $b->notes,
        ];
    }

    /** [from, to] as Y-m-d; defaults are offsets in days from today. */
    private function range(Request $request, int $fromOffset, int $toOffset, int $maxDays = 93): array
    {
        $today = CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfDay();
        $from = $this->date($request->query('from')) ?? $today->addDays($fromOffset);
        $to   = $this->date($request->query('to')) ?? $today->addDays($toOffset);
        if ($to->lt($from)) {
            $to = $from;
        }
        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->addDays($maxDays);
        }
        return [$from->toDateString(), $to->toDateString()];
    }

    private function date($value): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, ProductAvailability::TIMEZONE)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\MarketplaceOrganizer;
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
        ]);
    }

    // ------------------------------------------------------------------

    /** Bookings of this operator's products (optionally one location / product). */
    private function bookings(MarketplaceOrganizer $organizer, Request $request): Builder
    {
        return ActivityBooking::query()
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->whereHas('activity', fn ($q) => $q->where('marketplace_organizer_id', $organizer->id))
            ->when($request->query('location_id'), fn ($q, $id) => $q->whereHas('activity', fn ($a) => $a->where('location_id', (int) $id)))
            ->when($request->query('product_id'), fn ($q, $id) => $q->where('activity_id', (int) $id));
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

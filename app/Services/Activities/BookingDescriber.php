<?php

namespace App\Services\Activities;

use App\Models\ActivityBooking;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Human-readable description of activity bookings and tickets (Romanian),
 * shared by the confirmation e-mail, the ticket PDF and the customer's
 * account so all three say the same thing.
 */
class BookingDescriber
{
    private const MONTHS = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    private const DAYS   = [1 => 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă', 'duminică'];

    public static function isActivityOrder(Order $order): bool
    {
        return ($order->meta['order_type'] ?? null) === 'activity' || $order->source === 'marketplace_activity';
    }

    /**
     * The order's lines: one per top-level booking (a package's components
     * are listed inside it).
     */
    public static function lines(Order $order): array
    {
        $bookings = ActivityBooking::with([
            'activity.location.city', 'variant', 'tickets',
            'componentBookings.activity', 'componentBookings.variant', 'componentBookings.tickets',
        ])
            ->where('order_id', $order->id)
            ->whereNull('package_booking_id')
            ->orderBy('id')
            ->get();

        return $bookings->map(fn (ActivityBooking $b) => self::booking($b))->all();
    }

    public static function booking(ActivityBooking $b): array
    {
        $activity = $b->activity;
        $location = $activity?->location;

        return [
            'booking_id'        => $b->id,
            'confirmation_code' => $b->confirmation_code,
            'type'              => $activity?->product_type,
            'title'             => ActivityOrderBuilder::ro($activity?->title, 'Activitate'),
            'variant'           => ActivityOrderBuilder::ro($b->variant?->name, ''),
            'quantity'          => (int) ($b->quantity ?: 1),
            'date'              => $b->booking_date?->toDateString(),
            'end_date'          => $b->end_date?->toDateString(),
            'start_time'        => self::hm($b->getRawOriginal('slot_start_time')),
            'end_time'          => self::hm($b->getRawOriginal('slot_end_time')),
            'date_label'        => self::dateLabel($b),
            'time_label'        => self::timeLabel($b),
            'location'          => $location ? [
                'name'    => ActivityOrderBuilder::ro($location->name, ''),
                'address' => $location->address,
                'city'    => ActivityOrderBuilder::ro($location->city?->name, ''),
                'maps'    => $location->google_maps_url,
                'slug'    => $location->slug,
            ] : null,
            'meeting_point'     => $activity?->meeting_point,
            'vehicle_plate'     => $b->meta['vehicle_plate'] ?? null,
            'addons'            => (array) ($b->addons ?? []),
            'total'             => round(((int) $b->total_cents) / 100, 2),
            'image'             => CatalogPresenter::url($activity?->cover_image_url ?: $location?->cover_image_url),
            'status'            => $b->status,
            'tickets'           => $b->tickets,
            'components'        => $b->componentBookings->map(fn ($c) => [
                'title'      => ActivityOrderBuilder::ro($c->activity?->title, 'Produs'),
                'variant'    => ActivityOrderBuilder::ro($c->variant?->name, ''),
                'quantity'   => (int) ($c->quantity ?: 1),
                'time_label' => self::timeLabel($c),
                'tickets'    => $c->tickets,
            ])->all(),
        ];
    }

    /**
     * What a ticket is for, in the same shape the event tickets use: name,
     * date, time, venue, ticket type.
     */
    public static function ticket(Ticket $ticket): ?array
    {
        if (!$ticket->activity_booking_id) {
            return null;
        }
        $b = $ticket->relationLoaded('activityBooking') ? $ticket->activityBooking : ActivityBooking::with(['activity.location.city', 'variant', 'packageBooking.activity'])->find($ticket->activity_booking_id);
        if (!$b) {
            return null;
        }
        $activity = $b->activity;
        $location = $activity?->location;
        $companion = (bool) ($ticket->meta['companion'] ?? false);

        return [
            'name'          => ActivityOrderBuilder::ro($activity?->title, 'Activitate'),
            'type'          => $activity?->product_type,
            'ticket_type'   => $companion ? ($ticket->attendee_name ?: 'Însoțitor') : ActivityOrderBuilder::ro($b->variant?->name, 'Bilet'),
            'package'       => $b->packageBooking ? ActivityOrderBuilder::ro($b->packageBooking->activity?->title, '') : null,
            'date'          => $b->booking_date?->toDateString(),
            'end_date'      => $b->end_date?->toDateString(),
            'start_time'    => self::hm($b->getRawOriginal('slot_start_time')),
            'end_time'      => self::hm($b->getRawOriginal('slot_end_time')),
            'date_label'    => self::dateLabel($b),
            'time_label'    => self::timeLabel($b),
            'venue'         => $location ? ActivityOrderBuilder::ro($location->name, '') : null,
            'address'       => $location?->address,
            'city'          => $location ? ActivityOrderBuilder::ro($location->city?->name, '') : null,
            'meeting_point' => $activity?->meeting_point,
            'image'         => CatalogPresenter::url($activity?->cover_image_url ?: $location?->cover_image_url),
            'slug'          => $activity?->slug,
            'location_slug' => $location?->slug,
            'vehicle_plate' => $b->meta['vehicle_plate'] ?? null,
            'companion'     => $companion,
            'is_upcoming'   => ($b->end_date ?? $b->booking_date)?->toDateString() >= now('Europe/Bucharest')->toDateString(),
        ];
    }

    /** "sâmbătă, 25 septembrie 2026" or "25 – 26 septembrie 2026". */
    public static function dateLabel(ActivityBooking $b): string
    {
        if (!$b->booking_date) {
            return '';
        }
        $from = Carbon::parse($b->booking_date->toDateString());
        $to   = $b->end_date ? Carbon::parse($b->end_date->toDateString()) : null;
        if (!$to || $to->isSameDay($from)) {
            return self::DAYS[$from->dayOfWeekIso] . ', ' . $from->day . ' ' . self::MONTHS[$from->month] . ' ' . $from->year;
        }
        $left = $from->day . ($from->month !== $to->month ? ' ' . self::MONTHS[$from->month] : '') . ($from->year !== $to->year ? ' ' . $from->year : '');
        return $left . ' – ' . $to->day . ' ' . self::MONTHS[$to->month] . ' ' . $to->year;
    }

    /** "10:00 – 10:30", or "Valabil toată ziua" for day tickets. */
    public static function timeLabel(ActivityBooking $b): string
    {
        $start = self::hm($b->getRawOriginal('slot_start_time'));
        if (!$start) {
            return $b->end_date && $b->booking_date && !$b->end_date->isSameDay($b->booking_date)
                ? 'Valabil în toate zilele din interval'
                : 'Valabil toată ziua';
        }
        $end = self::hm($b->getRawOriginal('slot_end_time'));
        return $end ? $start . ' – ' . $end : $start;
    }

    private static function hm($value): ?string
    {
        return $value ? substr(ProductAvailability::time($value), 0, 5) : null;
    }
}

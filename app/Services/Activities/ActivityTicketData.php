<?php

namespace App\Services\Activities;

use App\Models\Ticket;
use Carbon\Carbon;

/**
 * Fills the ticket-template variables ({{event.name}}, {{venue.*}},
 * {{date.*}}, {{ticket.type}}) for an activity ticket. The generic resolver
 * looks for an event and leaves them blank; the same variables are used so
 * every ticket template works unchanged.
 */
class ActivityTicketData
{
    private const MONTHS = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    private const DAYS   = [1 => 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă', 'Duminică'];

    public static function apply(array $data, Ticket $ticket): array
    {
        $info = BookingDescriber::ticket($ticket);
        if (!$info) {
            return $data;
        }

        $data['event']['name'] = $info['name'];
        if (!empty($info['package'])) {
            $data['event']['description'] = 'Inclus în pachetul „' . $info['package'] . '”';
        }
        if ($info['image']) {
            $data['event']['image'] = $info['image'];
        }

        $data['venue']['name']    = $info['venue'] ?? '';
        $data['venue']['address'] = $info['address'] ?? '';
        $data['venue']['city']    = $info['city'] ?? '';

        $start = $info['date'] ? Carbon::parse($info['date']) : null;
        $end   = $info['end_date'] ? Carbon::parse($info['end_date']) : null;
        $fmt   = fn (Carbon $d) => $d->day . ' ' . self::MONTHS[$d->month] . ' ' . $d->year;
        $range = $start && $end && !$end->isSameDay($start);

        $timeRange = $info['start_time']
            ? $info['start_time'] . ($info['end_time'] ? ' – ' . $info['end_time'] : '')
            : '';
        $dayName = ($start && !$range) ? self::DAYS[$start->dayOfWeekIso] : '';

        $data['date'] = array_merge($data['date'] ?? [], [
            'start'           => $info['date'] ?? '',
            'start_formatted' => $start ? ($range ? $info['date_label'] : $fmt($start)) : '',
            'end'             => $info['end_date'] ?? '',
            'end_formatted'   => $end ? $fmt($end) : '',
            'time'            => $info['start_time'] ?? '',
            'end_time'        => $info['end_time'] ?? '',
            'time_range'      => $timeRange,
            'time_label'      => $timeRange !== '' ? 'Ora: ' . $timeRange : $info['time_label'],
            'doors_open'      => '',
            'doors_label'     => '',
            'day_name'        => $dayName,
            'full_text'       => implode(' | ', array_filter([
                $dayName,
                $start ? ($range ? $info['date_label'] : $fmt($start)) : '',
                $timeRange !== '' ? 'Ora: ' . $timeRange : $info['time_label'],
            ])),
        ]);

        $data['ticket']['type']           = $info['ticket_type'];
        $data['ticket']['visit_date']     = $info['date_label'];
        $data['ticket']['visit_date_raw'] = $info['date'] ?? '';
        $data['ticket']['visit_day_name'] = $dayName;

        return $data;
    }
}

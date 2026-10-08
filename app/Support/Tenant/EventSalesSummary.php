<?php

namespace App\Support\Tenant;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Carbon;

/**
 * Cifrele de vânzări ale unui eveniment de tenant, pentru tabul „Vânzări” din formularul evenimentului:
 * totaluri, defalcare pe tipuri de bilet, vânzări pe zile și ultimele comenzi.
 *
 * Comenzile se leagă de eveniment prin biletele lor (bilet → tip de bilet → eveniment), deci merge și
 * pentru comenzile care nu au orders.event_id completat. Doar comenzile plătite intră în cifre.
 */
class EventSalesSummary
{
    private const PAID = ['paid', 'confirmed', 'completed'];

    public static function for(Event $event): array
    {
        $types = TicketType::where('event_id', $event->id)->orderBy('id')->get();
        $typeIds = $types->pluck('id')->all();

        $empty = [
            'has_types' => $types->isNotEmpty(), 'currency' => $types->first()?->currency ?: 'RON',
            'tickets' => 0, 'revenue' => 0.0, 'gross' => 0.0, 'discounts' => 0.0, 'fees' => 0.0, 'orders' => 0, 'pending' => 0,
            'capacity' => 0, 'commission_rate' => 0.0, 'commission' => 0.0, 'types' => [], 'days' => [], 'recent' => [], 'last_sale' => null,
        ];
        if (! $typeIds) {
            return $empty;
        }

        // Bilete vândute pe tip (doar din comenzi plătite)
        $soldByType = Ticket::whereIn('ticket_type_id', $typeIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', self::PAID))
            ->selectRaw('ticket_type_id, COUNT(*) as sold')
            ->groupBy('ticket_type_id')
            ->pluck('sold', 'ticket_type_id');

        $orderIds = Ticket::whereIn('ticket_type_id', $typeIds)->whereNotNull('order_id')->distinct()->pluck('order_id');
        $orders = Order::whereIn('id', $orderIds)
            ->get(['id', 'status', 'total_cents', 'meta', 'customer_email', 'created_at']);
        $paid = $orders->whereIn('status', self::PAID);

        $rows = [];
        $gross = 0.0;
        $capacity = 0;
        foreach ($types as $type) {
            $price = ((int) ($type->price_cents ?: round(((float) ($type->price_max ?? 0)) * 100))) / 100;
            $sold = (int) ($soldByType[$type->id] ?? 0);
            $cap = (int) ($type->quota_total ?? 0);
            $capacity += max(0, $cap);
            $gross += $sold * $price;
            $rows[] = [
                'name'     => (string) $type->name,
                'status'   => (string) $type->status,
                'price'    => $price,
                'sold'     => $sold,
                'capacity' => $cap > 0 ? $cap : null,
                'percent'  => $cap > 0 ? min(100, (int) round($sold / $cap * 100)) : null,
                'revenue'  => $sold * $price,
            ];
        }

        $discounts = 0.0;
        $fees = 0.0;
        foreach ($paid as $order) {
            $meta = is_array($order->meta) ? $order->meta : [];
            $discounts += ((int) ($meta['discount_cents'] ?? 0)) / 100;
            $fees += ((int) ($meta['processing_fee_cents'] ?? 0)) / 100;
        }
        $revenue = $paid->sum('total_cents') / 100;

        // Vânzări pe zile, ultimele 14 zile (inclusiv azi)
        $days = [];
        $byDay = $paid->groupBy(fn ($o) => $o->created_at?->toDateString());
        $maxDay = 0.0;
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sum = (($byDay[$date->toDateString()] ?? collect())->sum('total_cents')) / 100;
            $maxDay = max($maxDay, $sum);
            $days[] = ['label' => $date->format('d.m'), 'total' => $sum, 'orders' => ($byDay[$date->toDateString()] ?? collect())->count()];
        }
        foreach ($days as &$day) {
            $day['height'] = $maxDay > 0 ? max(2, (int) round($day['total'] / $maxDay * 100)) : 2;
        }
        unset($day);

        $rate = 0.0;
        try {
            $rate = (float) $event->getEffectiveCommissionRate();
        } catch (\Throwable) {
            $rate = 0.0;
        }

        $recent = $orders->sortByDesc('created_at')->take(8)->map(fn ($o) => [
            'id'     => $o->id,
            'email'  => (string) $o->customer_email,
            'name'   => (string) ((is_array($o->meta) ? ($o->meta['customer_name'] ?? '') : '')),
            'total'  => ($o->total_cents ?? 0) / 100,
            'status' => (string) $o->status,
            'date'   => $o->created_at?->format('d.m.Y H:i'),
        ])->values()->all();

        return [
            'has_types'       => true,
            'currency'        => $types->first()?->currency ?: 'RON',
            'tickets'         => (int) $soldByType->sum(),
            'revenue'         => $revenue,
            'gross'           => $gross,
            'discounts'       => $discounts,
            'fees'            => $fees,
            'orders'          => $paid->count(),
            'pending'         => $orders->where('status', 'pending')->count(),
            'capacity'        => $capacity,
            'commission_rate' => $rate,
            // Comisionul platformei se calculează pe valoarea biletelor, fără taxa de procesare
            'commission'      => round(max(0, $revenue - $fees) * $rate / 100, 2),
            'types'           => $rows,
            'days'            => $days,
            'recent'          => $recent,
            'last_sale'       => $paid->max('created_at')?->format('d.m.Y H:i'),
        ];
    }
}

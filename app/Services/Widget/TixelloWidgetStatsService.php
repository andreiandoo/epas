<?php

namespace App\Services\Widget;

use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Agregatele pe care le arată widget-ul de Android.
 *
 * Regula de bază: toate cifrele sunt PESTE TOATĂ PLATFORMA — fiecare tenant
 * şi fiecare marketplace. Nu se aplică niciun scope de tenant, pentru că
 * widget-ul e al proprietarului Tixello, nu al unui organizator.
 *
 * Definiţiile („comandă plătită", „bilet emis", „comision") sunt aceleaşi cu
 * App\Filament\Widgets\StatsOverview, ca să nu ajungi la cifre diferite pe
 * telefon şi în panoul de admin. Singura diferenţă intenţionată e „azi": aici
 * ziua se taie în fusul din `config('tixello-widget.timezone')`
 * (Europe/Bucharest implicit), nu în UTC.
 */
class TixelloWidgetStatsService
{
    /**
     * Cursuri valutare memoizate pe durata cererii — `ExchangeRate::getRate`
     * face până la două interogări per apel şi e chemat în buclă.
     *
     * @var array<string, float|null>
     */
    private array $rateCache = [];

    /**
     * Payload-ul complet pentru un poll: cifre + ultimele comisioane.
     *
     * @param  int|null  $sinceCommissionId  ID-ul ultimei comenzi-cu-comision
     *                                       pe care telefonul o ştie deja.
     * @return array<string, mixed>
     */
    public function payload(?int $sinceCommissionId = null, ?int $commissionsLimit = null): array
    {
        $limit = $commissionsLimit ?? (int) config('tixello-widget.commissions_limit', 5);

        $recent = $this->recentCommissions($limit);

        return [
            'generated_at' => now()->toIso8601String(),
            'timezone' => $this->timezone(),
            'currency' => $this->currency(),
            'secondary_currency' => config('tixello-widget.secondary_currency'),
            'poll_interval_seconds' => (int) config('tixello-widget.poll_interval_seconds', 60),
            'stats' => $this->stats(),
            'commissions' => $recent,
            /* Doar comisioanele pe care telefonul NU le-a văzut încă. Ele
               declanşează sunetul/vibraţia; restul listei e doar afişaj. */
            'new_commissions' => $sinceCommissionId === null
                ? []
                : array_values(array_filter($recent, fn ($c) => $c['id'] > $sinceCommissionId)),
            'cursor' => [
                'last_commission_id' => $recent[0]['id'] ?? ($sinceCommissionId ?? 0),
            ],
        ];
    }

    /**
     * Cifrele agregate. Trec prin cache — `orders` şi `tickets` sunt tabele
     * mari, iar mai multe telefoane pot bate în acelaşi timp.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $ttl = (int) config('tixello-widget.cache_ttl', 20);

        $build = fn () => $this->buildStats();

        return $ttl > 0
            ? Cache::remember('tixello_widget:stats', $ttl, $build)
            : $build();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        [$dayStart, $dayEnd] = $this->todayRange();
        $paidStatuses = $this->paidStatuses();

        /* --- Vânzări (valoare + număr de comenzi) ------------------------ */
        $salesTotal = $this->orderMoney(null, null);
        $salesToday = $this->orderMoney($dayStart, $dayEnd);

        /* --- Bilete ------------------------------------------------------ */
        $ticketsTotal = Ticket::where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
            ->count();
        $ticketsToday = Ticket::where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->count();

        /* --- Clienţi (tenant + marketplace, la un loc) -------------------- */
        $customersTenant = Customer::count();
        $customersMarketplace = MarketplaceCustomer::count();
        $customersTenantToday = Customer::whereBetween('created_at', [$dayStart, $dayEnd])->count();
        $customersMarketplaceToday = MarketplaceCustomer::whereBetween('created_at', [$dayStart, $dayEnd])->count();

        return [
            'sales' => [
                'total' => $salesTotal['value'],
                'total_secondary' => $salesTotal['secondary'],
                'total_orders' => $salesTotal['orders'],
                'today' => $salesToday['value'],
                'today_secondary' => $salesToday['secondary'],
                'today_orders' => $salesToday['orders'],
            ],
            'tickets' => [
                'total' => $ticketsTotal,
                'today' => $ticketsToday,
            ],
            'customers' => [
                'total' => $customersTenant + $customersMarketplace,
                'today' => $customersTenantToday + $customersMarketplaceToday,
                'tenant' => $customersTenant,
                'marketplace' => $customersMarketplace,
            ],
            'revenue' => [
                /* „Venituri Tixello" = comisionul reţinut de platformă. */
                'total' => $salesTotal['commission'],
                'total_secondary' => $salesTotal['commission_secondary'],
                'today' => $salesToday['commission'],
                'today_secondary' => $salesToday['commission_secondary'],
            ],
        ];
    }

    /**
     * Sume de bani pe comenzi plătite, convertite în moneda de raportare.
     *
     * Agregarea se face pe monedă într-o singură interogare şi abia apoi se
     * converteşte, pentru că `orders.total` şi `orders.commission_amount` sunt
     * în moneda comenzii, nu într-una comună.
     *
     * @return array{value: float, secondary: float|null, commission: float, commission_secondary: float|null, orders: int}
     */
    private function orderMoney(?Carbon $from, ?Carbon $to): array
    {
        $query = Order::query()
            ->whereIn('status', $this->paidStatuses());

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $rows = $query
            ->selectRaw('currency, COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_sum, COALESCE(SUM(total_cents), 0) as cents_sum, COALESCE(SUM(commission_amount), 0) as commission_sum')
            ->groupBy('currency')
            ->get();

        $currency = $this->currency();
        $value = 0.0;
        $commission = 0.0;
        $orders = 0;

        foreach ($rows as $row) {
            /* `currency` e nullable pe comenzi vechi — le tratăm ca fiind în
               moneda de raportare, exact ca dashboard-ul de admin. */
            $rowCurrency = strtoupper((string) ($row->currency ?: $currency));

            /* Comenzile vechi ţin suma în `total_cents`, cele noi în `total`.
               Aceeaşi cădere ca în StatsOverview::sumByCurrency(). */
            $rowTotal = (float) $row->total_sum;
            if ($rowTotal == 0.0) {
                $rowTotal = ((float) $row->cents_sum) / 100;
            }

            $rate = $this->rate($rowCurrency, $currency);

            /* Fără curs nu inventăm unul: suma rămâne neconvertită şi ar
               umfla totalul. Mai bine o lăsăm afară şi rămâne vizibil în
               `orders_count` că a existat volum. */
            if ($rate === null) {
                $orders += (int) $row->orders_count;
                continue;
            }

            $value += $rowTotal * $rate;
            $commission += ((float) $row->commission_sum) * $rate;
            $orders += (int) $row->orders_count;
        }

        return [
            'value' => round($value, 2),
            'secondary' => $this->toSecondary($value),
            'commission' => round($commission, 2),
            'commission_secondary' => $this->toSecondary($commission),
            'orders' => $orders,
        ];
    }

    /**
     * Ultimele comisioane câştigate, cu evenimentul din care vin.
     *
     * Nu trece prin cache: e semnalul care declanşează alerta pe telefon.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentCommissions(int $limit): array
    {
        $limit = max(1, min($limit, (int) config('tixello-widget.commissions_max_limit', 50)));

        $orders = Order::query()
            ->whereIn('status', $this->paidStatuses())
            ->where('commission_amount', '>', 0)
            ->with([
                'event:id,title',
                'marketplaceEvent:id,name',
                'tenant:id,name',
                'marketplaceClient:id,name',
                'items:id,order_id,name',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'order_number', 'currency', 'commission_amount', 'total', 'total_cents',
                'event_id', 'marketplace_event_id', 'tenant_id', 'marketplace_client_id',
                'paid_at', 'created_at',
            ]);

        $currency = $this->currency();

        return $orders->map(function (Order $order) use ($currency) {
            $orderCurrency = strtoupper((string) ($order->currency ?: $currency));
            $amount = (float) $order->commission_amount;
            $rate = $this->rate($orderCurrency, $currency);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'event' => $this->eventLabel($order),
                'source' => $order->marketplaceClient?->name ?? $order->tenant?->name,
                'amount' => round($amount, 2),
                'amount_currency' => $orderCurrency,
                /* null când nu există curs — aplicaţia afişează atunci suma
                   în moneda ei originală, nu o cifră inventată. */
                'amount_converted' => $rate === null ? null : round($amount * $rate, 2),
                'currency' => $currency,
                'at' => ($order->paid_at ?? $order->created_at)?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * Numele evenimentului din care vine comisionul. Comenzile de tenant şi
     * cele de marketplace ţin evenimentul în coloane diferite, iar unele
     * comenzi vechi nu-l au deloc pe comandă — de aici cascada.
     */
    private function eventLabel(Order $order): string
    {
        /* `Event::name` scoate un string dintr-un titlu traductibil
           (`{"ro": "...", "en": "..."}`); `title` brut ar ajunge array în
           payload. La marketplace, `name` e deja o coloană simplă. */
        $title = $order->event?->name
            ?: $order->marketplaceEvent?->name
            ?: $order->items->first()?->name;

        return $title !== null && trim((string) $title) !== ''
            ? (string) $title
            : 'Eveniment necunoscut';
    }

    /**
     * Ziua curentă în fusul configurat, exprimată în marginile pe care le
     * înţelege baza (coloanele sunt în UTC).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayRange(): array
    {
        $tz = $this->timezone();
        $start = Carbon::now($tz)->startOfDay()->utc();
        $end = Carbon::now($tz)->endOfDay()->utc();

        return [$start, $end];
    }

    private function toSecondary(float $amountInPrimary): ?float
    {
        $secondary = config('tixello-widget.secondary_currency');

        if (! $secondary) {
            return null;
        }

        $rate = $this->rate($this->currency(), strtoupper($secondary));

        return $rate === null ? null : round($amountInPrimary * $rate, 2);
    }

    private function rate(string $from, string $to): ?float
    {
        $key = "{$from}>{$to}";

        if (! array_key_exists($key, $this->rateCache)) {
            $this->rateCache[$key] = ExchangeRate::getRate($from, $to);
        }

        return $this->rateCache[$key];
    }

    private function currency(): string
    {
        return strtoupper((string) config('tixello-widget.currency', 'EUR'));
    }

    private function timezone(): string
    {
        return (string) config('tixello-widget.timezone', 'Europe/Bucharest');
    }

    /**
     * @return array<int, string>
     */
    private function paidStatuses(): array
    {
        return (array) config('tixello-widget.paid_statuses', ['paid', 'confirmed', 'completed']);
    }
}

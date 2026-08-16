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

        /* Alertele NU se filtrează din lista de afişare: dacă între două
           interogări au intrat 9 comisioane, iar widget-ul arată 5, filtrarea
           listei ar înghiţi 4 alerte. De aceea „ce e nou" e o interogare
           proprie, plafonată separat. */
        $new = $sinceCommissionId === null
            ? []
            : $this->recentCommissions($this->newCommissionsCap(), $sinceCommissionId);

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
            'new_commissions' => $new,
            'cursor' => [
                /* Maximul dintre ce s-a afişat şi ce s-a alertat: cu lista de
                   afişare goală (comisioane vechi, fără nimic nou) cursorul nu
                   trebuie să dea înapoi. */
                'last_commission_id' => max(
                    $recent[0]['id'] ?? 0,
                    $new[0]['id'] ?? 0,
                    $sinceCommissionId ?? 0,
                ),
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
        /* Două cache-uri, nu unul.
         *
         * Cifrele „all time" cer un COUNT/SUM peste tot tabelul — pe producţie
         * `tickets` nu are index pe `status`, deci e scanare completă. Ele se
         * mişcă lent în procente, aşa că pot sta minute întregi în cache.
         * Cifrele de azi sunt mărginite de un interval de dată (deci ieftine)
         * şi sunt exact cele la care te uiţi des, deci rămân proaspete.
         *
         * Fără separarea asta, un TTL scurt ţinea scanarea completă în buclă
         * cât timp exista un telefon care întreba. */
        $allTimeTtl = (int) config('tixello-widget.cache_ttl_all_time', 120);
        $todayTtl = (int) config('tixello-widget.cache_ttl', 20);

        $allTime = $this->cached('tixello_widget:stats:all-time', $allTimeTtl, fn () => $this->buildAllTimeStats());
        $today = $this->cached('tixello_widget:stats:today', $todayTtl, fn () => $this->buildTodayStats());

        return [
            'sales' => [
                'total' => $allTime['sales']['value'],
                'total_secondary' => $allTime['sales']['secondary'],
                'total_orders' => $allTime['sales']['orders'],
                'today' => $today['sales']['value'],
                'today_secondary' => $today['sales']['secondary'],
                'today_orders' => $today['sales']['orders'],
            ],
            'tickets' => [
                'total' => $allTime['tickets'],
                'today' => $today['tickets'],
            ],
            'customers' => [
                'total' => $allTime['customers_tenant'] + $allTime['customers_marketplace'],
                'today' => $today['customers'],
                'tenant' => $allTime['customers_tenant'],
                'marketplace' => $allTime['customers_marketplace'],
            ],
            'revenue' => [
                /* „Venituri Tixello" = comisionul reţinut de platformă. */
                'total' => $allTime['sales']['commission'],
                'total_secondary' => $allTime['sales']['commission_secondary'],
                'today' => $today['sales']['commission'],
                'today_secondary' => $today['sales']['commission_secondary'],
            ],
        ];
    }

    /**
     * @template T
     * @param  \Closure(): T  $build
     * @return T
     */
    private function cached(string $key, int $ttl, \Closure $build): mixed
    {
        return $ttl > 0 ? Cache::remember($key, $ttl, $build) : $build();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAllTimeStats(): array
    {
        return [
            'sales' => $this->orderMoney(null, null),
            'tickets' => $this->ticketsQuery()->count(),
            'customers_tenant' => Customer::count(),
            'customers_marketplace' => MarketplaceCustomer::count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTodayStats(): array
    {
        [$dayStart, $dayEnd] = $this->todayRange();

        return [
            'sales' => $this->orderMoney($dayStart, $dayEnd),
            'tickets' => $this->ticketsQuery()
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count(),
            'customers' => Customer::whereBetween('created_at', [$dayStart, $dayEnd])->count()
                + MarketplaceCustomer::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
        ];
    }

    /**
     * Biletele emise de Tixello: valide şi dintr-o comandă încasată.
     */
    private function ticketsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->whereIn('status', $this->paidStatuses()));
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
            $query->whereBetween($this->todayColumn(), [$from, $to]);
        }

        /* Căderea `total` → `total_cents` se face PE RÂND, nu pe sumă.
           Varianta „dacă suma e 0, ia centii" (cea din panoul de admin) pierde
           tăcut fiecare comandă veche dintr-un grup în care există şi comenzi
           noi — adică fix ce se întâmplă acum, după migrarea pe `total`. */
        $rows = $query
            ->selectRaw(
                'currency, COUNT(*) as orders_count,'
                . ' COALESCE(SUM(CASE WHEN COALESCE(total, 0) <> 0 THEN total ELSE COALESCE(total_cents, 0) / 100.0 END), 0) as total_sum,'
                . ' COALESCE(SUM(commission_amount), 0) as commission_sum'
            )
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

            $rowTotal = (float) $row->total_sum;
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
    public function recentCommissions(int $limit, ?int $sinceId = null): array
    {
        $limit = max(1, min($limit, (int) config('tixello-widget.commissions_max_limit', 50)));

        $orders = Order::query()
            ->whereIn('status', $this->paidStatuses())
            ->where('commission_amount', '>', 0)
            ->when($sinceId !== null, fn ($q) => $q->where('id', '>', $sinceId))
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

    /**
     * Coloana după care se taie „azi" pe comenzi.
     *
     * `created_at` (implicit) = când a intrat comanda, identic cu panoul de
     * admin. `paid_at` = când a intrat banul — mai aproape de ce înţelege un
     * proprietar prin „vândut azi", dar mută o comandă făcută aseară şi
     * plătită azi-dimineaţă în ziua de azi.
     */
    private function todayColumn(): string
    {
        return config('tixello-widget.today_basis') === 'paid_at' ? 'paid_at' : 'created_at';
    }

    /**
     * Câte comisioane pot intra într-o singură rundă de alerte. Plafon, ca un
     * telefon întors după o săptămână offline să nu primească 300 de sunete.
     */
    private function newCommissionsCap(): int
    {
        return (int) config('tixello-widget.new_commissions_cap', 20);
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

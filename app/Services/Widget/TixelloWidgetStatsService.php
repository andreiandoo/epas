<?php

namespace App\Services\Widget;

use App\Models\Artist;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    public function __construct(private readonly TixelloRevenueService $revenue)
    {
    }

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
        $monthlyTtl = (int) config('tixello-widget.cache_ttl_monthly', 300);

        $allTime = $this->cached('tixello_widget:stats:all-time', $allTimeTtl, fn () => $this->buildAllTimeStats());
        $today = $this->cached('tixello_widget:stats:today', $todayTtl, fn () => $this->buildTodayStats());
        $monthly = $this->cached('tixello_widget:stats:monthly', $monthlyTtl, fn () => $this->buildMonthlyStats());

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
            'artists' => [
                'total' => $allTime['artists'],
                'today' => $today['artists'],
            ],
            'venues' => [
                'total' => $allTime['venues'],
                'today' => $today['venues'],
            ],
            'revenue' => [
                /* „Venituri Tixello" = ce încasează platforma: comision din
                   bilete (rata fiecărui tenant / marketplace) + partea din
                   servicii + taxele one-time. NU `orders.commission_amount` —
                   acela e comisionul marketplace-ului către organizatorii lui. */
                'total' => $allTime['revenue']['total'],
                'total_secondary' => $this->toSecondary($allTime['revenue']['total']),
                'today' => $today['revenue']['total'],
                'today_secondary' => $this->toSecondary($today['revenue']['total']),
                /* Defalcare, ca să se vadă din ce se compune cifra mare. */
                'tickets_total' => $allTime['revenue']['tickets'],
                'tickets_today' => $today['revenue']['tickets'],
                'services_total' => $allTime['revenue']['services'],
                'services_today' => $today['revenue']['services'],
                'one_time_total' => $allTime['revenue']['one_time'],
                'one_time_today' => $today['revenue']['one_time'],
                /* Abonamentele lunare sunt o RATĂ, nu o sumă acumulată: a le
                   aduna în „all time" ar produce o cifră fără sens. Widget-ul
                   le arată separat, ca „+X/lună". */
                'recurring_monthly' => $allTime['revenue']['recurring_monthly'],
            ],
            /* Luna curentă vs luna trecută — pentru banda de comparație din
               widget. Cache separat (5 min default) — cifrele lunare nu se
               mișcă la fel de repede ca „azi", nu are rost refresh la 20s. */
            'monthly' => [
                'current' => $monthly['current'],
                'previous' => $monthly['previous'],
                'current_label' => $monthly['current_label'],
                'previous_label' => $monthly['previous_label'],
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
            'artists' => Artist::count(),
            'venues' => Venue::count(),
            'revenue' => $this->revenueFor(null, null),
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
            'artists' => Artist::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'venues' => Venue::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            'revenue' => $this->revenueFor($dayStart, $dayEnd),
        ];
    }

    /**
     * Luna curentă vs luna precedentă — folosit pentru banda de comparație
     * de sub Venit Tixello. Intervalele se taie în fusul configurat.
     *
     * @return array<string, mixed>
     */
    private function buildMonthlyStats(): array
    {
        $tz = $this->timezone();
        $now = Carbon::now($tz);

        $currentStart = $now->copy()->startOfMonth()->utc();
        $currentEnd = $now->copy()->endOfMonth()->utc();
        $previousMonthCarbon = $now->copy()->subMonthNoOverflow();
        $previousStart = $previousMonthCarbon->copy()->startOfMonth()->utc();
        $previousEnd = $previousMonthCarbon->copy()->endOfMonth()->utc();

        return [
            'current' => $this->monthlySlice($currentStart, $currentEnd),
            'previous' => $this->monthlySlice($previousStart, $previousEnd),
            'current_label' => $now->translatedFormat('M Y'),      // ex. „Aug 2026"
            'previous_label' => $previousMonthCarbon->translatedFormat('M Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlySlice(Carbon $from, Carbon $to): array
    {
        return [
            'sales' => $this->orderMoney($from, $to),
            'tickets' => $this->ticketsQuery()->whereBetween('created_at', [$from, $to])->count(),
            'customers' => Customer::whereBetween('created_at', [$from, $to])->count()
                + MarketplaceCustomer::whereBetween('created_at', [$from, $to])->count(),
            'artists' => Artist::whereBetween('created_at', [$from, $to])->count(),
            'venues' => Venue::whereBetween('created_at', [$from, $to])->count(),
            'revenue' => $this->revenueFor($from, $to),
        ];
    }

    /**
     * Veniturile Tixello pentru un interval (sau all time, cu null/null).
     *
     * Fiecare sursă vine pe monede, deci conversia se face aici, o singură
     * dată. Ce n-are curs rămâne afară, ca peste tot — nu inventăm cursuri.
     *
     * @return array<string, float>
     */
    private function revenueFor(?Carbon $from, ?Carbon $to): array
    {
        $tickets = $this->convertByCurrency(
            $this->revenue->ticketCommissionByCurrency($from, $to, $this->todayColumn())
        );
        $services = $this->convertByCurrency(
            $this->revenue->serviceRevenueByCurrency($from, $to)
        );
        $oneTime = round($this->revenue->oneTimeRevenue($from, $to), 2);

        return [
            'tickets' => $tickets,
            'services' => $services,
            'one_time' => $oneTime,
            'total' => round($tickets + $services + $oneTime, 2),
            'recurring_monthly' => round($this->revenue->monthlyRecurringRevenue(), 2),
        ];
    }

    /**
     * @param  array<string, float>  $byCurrency
     */
    private function convertByCurrency(array $byCurrency): float
    {
        $target = $this->currency();
        $total = 0.0;

        foreach ($byCurrency as $currency => $amount) {
            $rate = $this->rate(strtoupper($currency), $target);

            if ($rate === null) {
                continue;
            }

            $total += $amount * $rate;
        }

        return round($total, 2);
    }

    /**
     * Toate biletele reale de pe platformă — INCLUSIV cele importate din
     * sistemul vechi WordPress AmBilet (legacy_import) sau din alte platforme
     * (external_import). Widget-ul „cifre" arată activitatea totală, nu doar
     * ce a generat comision pentru Tixello.
     *
     * Filtrul de status păstrează doar biletele reale (valid, used,
     * checked_in) — excludem cancelled/void/refunded pentru că acelea nu mai
     * există efectiv. Nu filtrăm după statusul comenzii sau după sursă:
     * user-ul a spus explicit că se așteaptă la o cifră apropiată de
     * `Ticket::count()` din admin (~350k), NU la cifra restrânsă pe orders
     * plătite prin Tixello (~48k).
     */
    private function ticketsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()
            ->whereIn('status', ['valid', 'used', 'checked_in']);
    }

    /**
     * Sume de bani pe comenzi plătite, convertite în moneda de raportare.
     *
     * Agregarea se face pe monedă într-o singură interogare şi abia apoi se
     * converteşte, pentru că sumele sunt în moneda comenzii, nu într-una
     * comună.
     *
     * @return array{value: float, secondary: float|null, orders: int}
     */
    private function orderMoney(?Carbon $from, ?Carbon $to): array
    {
        /* Vânzări = activitate totală platformă (bani mișcați prin sistem),
           INCLUSIV import-urile legacy (comenzi reale din WordPress vechi
           migrate în Tixello). Nu excludem sursele — aliniat cu admin
           StatsOverview care afișează venituri totale, nu doar cele
           generate prin Tixello. Excluderea import-urilor rămâne doar pe
           calculul de comision (TixelloRevenueService), unde e corect —
           Tixello nu a încasat comision pe importuri istorice. */
        $query = Order::query()
            ->whereIn('status', $this->saleStatuses());

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
                . ' COALESCE(SUM(CASE WHEN COALESCE(total, 0) <> 0 THEN total ELSE COALESCE(total_cents, 0) / 100.0 END), 0) as total_sum'
            )
            ->groupBy('currency')
            ->get();

        $currency = $this->currency();
        $value = 0.0;
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
            $orders += (int) $row->orders_count;
        }

        return [
            'value' => round($value, 2),
            'secondary' => $this->toSecondary($value),
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
            ->whereIn('status', $this->saleStatuses())
            /* Comisioanele istorice (import legacy/external) sunt excluse din
               lista de „ultimele comisioane" pentru că n-au adus venit real
               Tixello — au fost migrate cu comisionul deja încasat prin
               sistemul vechi. Aceeași excludere e aplicată și în
               TixelloRevenueService pentru cifra „Venituri Tixello". */
            ->whereNotIn('source', TixelloRevenueService::EXCLUDED_SOURCES)
            /* Comisionul Tixello e rata clientului × valoarea comenzii, deci
               „are comision" înseamnă „clientul are o rată > 0". Filtrul stă în
               SQL, altfel ar trebui citite comenzi la întâmplare până se adună
               cinci cu comision. */
            ->where(function ($q) {
                $q->whereExists(fn ($sub) => $sub->select(DB::raw(1))
                    ->from('tenants')
                    ->whereColumn('tenants.id', 'orders.tenant_id')
                    ->where('tenants.commission_rate', '>', 0))
                    ->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('marketplace_clients')
                        ->whereColumn('marketplace_clients.id', 'orders.marketplace_client_id')
                        ->where('marketplace_clients.commission_rate', '>', 0));
            })
            ->when($sinceId !== null, fn ($q) => $q->where('id', '>', $sinceId))
            ->with([
                'event:id,title',
                'marketplaceEvent:id,name',
                'tenant:id,name,commission_rate',
                'marketplaceClient:id,name,commission_rate',
                'items:id,order_id,name',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id', 'order_number', 'currency', 'total', 'total_cents',
                'event_id', 'marketplace_event_id', 'tenant_id', 'marketplace_client_id',
                'paid_at', 'created_at',
            ]);

        $currency = $this->currency();

        return $orders->map(function (Order $order) use ($currency) {
            /* Partea Tixello din comanda asta — rata tenantului sau a
               marketplace-ului, aplicată pe valoarea comenzii. */
            $cut = $this->revenue->commissionForOrder($order);
            $orderCurrency = $cut['currency'];
            $amount = $cut['amount'];
            $rate = $this->rate($orderCurrency, $currency);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'event' => $this->eventLabel($order),
                'source' => $cut['source'],
                'rate' => $cut['rate'],
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
    private function saleStatuses(): array
    {
        /* Sursa unica: aceleasi statusuri pe care le foloseste calculul de
           venit. Retururile nu sterg o vanzare care a avut loc. */
        return (array) config('tixello-widget.sale_statuses', TixelloRevenueService::SALE_STATUSES);
    }
}

<?php

namespace App\Services\Widget;

use App\Models\MarketplaceClientMicroservice;
use App\Models\Order;
use App\Models\ServiceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Veniturile Tixello core — banii care ajung la platformă, nu la altcineva.
 *
 * DE CE EXISTĂ SEPARAT DE `orders.commission_amount`
 * --------------------------------------------------
 * `orders.commission_amount` e comisionul MARKETPLACE-ULUI către organizatorii
 * lui (vezi `config/tixello.php`: „evenimentele de marketplace poartă
 * comisionul marketplace-ului"). Banii ăia nu ajung niciodată la Tixello, iar
 * coloana e 0 pe vânzările POS/leisure. Însumarea ei ca „venit Tixello" e pur
 * și simplu banul altcuiva.
 *
 * FORMULELE, LUATE DIN LOCUL CARE CHIAR FACTUREAZĂ
 * ------------------------------------------------
 * Sursa de adevăr e panoul core (`App\Filament\Pages\BillingOverview`) plus
 * comanda `invoices:generate-tenant`, care emite facturile:
 *
 *   tenant       → `tenants.commission_rate` % × valoarea comenzilor tenantului
 *   marketplace  → `marketplace_clients.commission_rate` % × valoarea comenzilor
 *   servicii     → `ServiceOrder::TIXELLO_SHARE` (50%) din serviciile plătite
 *   abonamente   → `billing_amount` pe microserviciile active
 *
 * Un detaliu care contează: comenzile de tenant țin banii în `total_cents`, iar
 * cele de marketplace în `total`. Aici se citesc amândouă, pe fiecare rând.
 *
 * RETURURILE NU SCAD NIMIC
 * ------------------------
 * Regulă de afaceri: Tixello încasează comision pe vânzare, iar restituirile —
 * integrale sau parțiale — nu i-l iau înapoi. De aceea statusurile `refunded` și
 * `partially_refunded` intră la fel ca `paid`. Aceeași alegere o face și
 * `invoices:generate-tenant`, care exclude doar `cancelled`.
 */
class TixelloRevenueService
{
    /**
     * Statusurile de comandă care înseamnă „vânzarea a avut loc și încă e reală".
     *
     * ALINIAT CU ADMIN /admin (StatsOverview) — aceleași 3 statusuri. Nu mai
     * includem refunded/partially_refunded ca să evităm ca widget-ul să
     * raporteze cifre mai mari decât panoul de admin. Efectul concret e că
     * o comandă rambursată integral nu mai apare ca venit; una rambursată
     * parțial, la fel — dispare complet din cifre, nu doar cu diferența.
     * Regula asta e cea aleasă și de invoices:generate-tenant.
     */
    public const SALE_STATUSES = ['paid', 'confirmed', 'completed'];

    /**
     * Sursele de comandă care NU sunt vânzări reale prin platformă:
     *   test_order      — POS smoke-test (10 lei/bilet, meta.is_test=true)
     *   legacy_import   — import unic din sistemul vechi WordPress AmBilet
     *   external_import — comenzi importate din alte sisteme (Njoy, etc.)
     *
     * Le excludem din TOATE cifrele widget-ului (bilete, vânzări, comision)
     * pentru că altfel Tixello încasează "comision" pe orders vechi care
     * nu i-au adus niciun ban efectiv. Aceeași excludere o face
     * SalesBreakdownService + BillingBreakdown (sursa de adevăr).
     */
    public const EXCLUDED_SOURCES = ['test_order', 'legacy_import', 'external_import'];

    /**
     * Comisionul Tixello din vânzarea de bilete, pe monedă.
     *
     * @return array<string, float>  [monedă => sumă]
     */
    public function ticketCommissionByCurrency(?Carbon $from = null, ?Carbon $to = null, string $dateColumn = 'created_at'): array
    {
        $out = [];

        $sources = [
            $this->tenantCommissionQuery($from, $to, $dateColumn),
            $this->marketplaceCommissionQuery($from, $to, $dateColumn),
        ];

        foreach ($sources as $rows) {
            foreach ($rows as $row) {
                $currency = strtoupper((string) ($row->currency ?: config('tixello-widget.currency', 'EUR')));
                $out[$currency] = ($out[$currency] ?? 0) + (float) $row->commission;
            }
        }

        return $out;
    }

    /**
     * Comenzile de tenant: rata proprie a fiecărui tenant × valoarea BILETELOR
     * vândute prin acele comenzi.
     *
     * BAZA E `tickets.price` (face value), NU `orders.total`. Aceeași alegere
     * o face BillingBreakdown (sursa de adevăr): "Baza pentru cei X% Tixello
     * se ia din valoarea biletelor" — `orders.total` include fees + comision
     * peste preț, ceea ce ar re-taxa comisionul deja adăugat de organizator.
     * Numai biletele status='valid' sau 'used' contează (excludem
     * refunded/cancelled/void/invitation care oricum n-au venit real).
     */
    private function tenantCommissionQuery(?Carbon $from, ?Carbon $to, string $dateColumn)
    {
        return $this->baseOrderQuery($from, $to, $dateColumn)
            ->join('tickets', 'tickets.order_id', '=', 'orders.id')
            ->join('tenants', 'tenants.id', '=', 'orders.tenant_id')
            ->whereNull('orders.marketplace_client_id')
            ->whereIn('tickets.status', ['valid', 'used'])
            ->selectRaw(
                'orders.currency as currency, COALESCE(SUM(COALESCE(tickets.price, 0)'
                . ' * COALESCE(tenants.commission_rate, 0) / 100), 0) as commission'
            )
            ->groupBy('orders.currency')
            ->get();
    }

    /**
     * Comenzile de marketplace: rata marketplace-ului către Tixello,
     * aplicată pe face value bilete vândute (vezi comentariul de mai sus).
     */
    private function marketplaceCommissionQuery(?Carbon $from, ?Carbon $to, string $dateColumn)
    {
        return $this->baseOrderQuery($from, $to, $dateColumn)
            ->join('tickets', 'tickets.order_id', '=', 'orders.id')
            ->join('marketplace_clients', 'marketplace_clients.id', '=', 'orders.marketplace_client_id')
            ->whereIn('tickets.status', ['valid', 'used'])
            ->selectRaw(
                'orders.currency as currency, COALESCE(SUM(COALESCE(tickets.price, 0)'
                . ' * COALESCE(marketplace_clients.commission_rate, 0) / 100), 0) as commission'
            )
            ->groupBy('orders.currency')
            ->get();
    }

    /**
     * Baza queryului pe orders — status vânzare + exclude sursele de import
     * legacy/external/test care nu reprezintă tranzacții reale prin platformă.
     */
    private function baseOrderQuery(?Carbon $from, ?Carbon $to, string $dateColumn)
    {
        $query = DB::table('orders')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereNotIn('orders.source', self::EXCLUDED_SOURCES);

        if ($from && $to) {
            $query->whereBetween('orders.' . $dateColumn, [$from, $to]);
        }

        return $query;
    }

    /**
     * Partea Tixello din serviciile vândute (featuring, campanii etc.).
     *
     * @return array<string, float>
     */
    public function serviceRevenueByCurrency(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = ServiceOrder::query()->where('payment_status', ServiceOrder::PAYMENT_PAID);

        if ($from && $to) {
            /* `paid_at` e momentul în care a intrat banul; o comandă de
               serviciu poate sta zile în `pending_payment`. */
            $query->whereBetween('paid_at', [$from, $to]);
        }

        return $query
            ->selectRaw('currency, COALESCE(SUM(total), 0) as total')
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row) => [
                strtoupper((string) ($row->currency ?: config('tixello-widget.currency', 'EUR')))
                    => round((float) $row->total * ServiceOrder::TIXELLO_SHARE, 2),
            ])
            ->all();
    }

    /**
     * Venitul recurent lunar din microservicii active.
     *
     * NU se adună în totalul „all time": e o rată (bani pe lună), nu o sumă
     * acumulată. A o aduna ar produce o cifră care nu înseamnă nimic. Widget-ul
     * o arată separat, ca „+X/lună".
     */
    public function monthlyRecurringRevenue(): float
    {
        return (float) MarketplaceClientMicroservice::query()
            ->where('is_active', true)
            ->where('billing_cycle', 'monthly')
            ->sum('billing_amount');
    }

    /**
     * Taxele one-time din microservicii activate.
     *
     * Doar rândurile CU `activated_at` completat contează — draft-urile
     * (client bifat dar niciodată activat / plătit) NU sunt venit real.
     * Fără filtrul ăsta, orice row nou cu billing_cycle='one_time' intra
     * în total chiar dacă banii n-au ajuns niciodată.
     */
    public function oneTimeRevenue(?Carbon $from = null, ?Carbon $to = null): float
    {
        $query = MarketplaceClientMicroservice::query()
            ->where('billing_cycle', 'one_time')
            ->whereNotNull('activated_at');

        if ($from && $to) {
            $query->whereBetween('activated_at', [$from, $to]);
        }

        return (float) $query->sum('billing_amount');
    }

    /**
     * Comisionul Tixello pentru o singură comandă — pentru lista de comisioane
     * și pentru alerte. Baza e aceeași ca la agregatele mari: suma
     * `tickets.price` pentru biletele valid/used ale acestei comenzi,
     * NU `orders.total` (care include fees / comision peste preț).
     *
     * @return array{amount: float, currency: string, rate: float, source: string|null}
     */
    public function commissionForOrder(Order $order): array
    {
        $value = (float) DB::table('tickets')
            ->where('order_id', $order->id)
            ->whereIn('status', ['valid', 'used'])
            ->sum('price');

        $rate = 0.0;
        $source = null;

        if ($order->marketplace_client_id) {
            $rate = (float) ($order->marketplaceClient?->commission_rate ?? 0);
            $source = $order->marketplaceClient?->name;
        } elseif ($order->tenant_id) {
            $rate = (float) ($order->tenant?->commission_rate ?? 0);
            $source = $order->tenant?->name;
        }

        return [
            'amount' => round($value * ($rate / 100), 2),
            'currency' => strtoupper((string) ($order->currency ?: config('tixello-widget.currency', 'EUR'))),
            'rate' => $rate,
            'source' => $source,
        ];
    }
}

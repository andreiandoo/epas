<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit al cardului „Sold disponibil” din /marketplace/balances. Doar citește.
 *
 *  1. Cardul = Σ available_balance salvat; îl refac din netul pe evenimente
 *     (același calcul ca soldul) și îl descompun pe categorii.
 *  2. Netul vânzărilor Tixello refăcut independent, din comenzi: încasat −
 *     comision reținut la checkout − extra; comparat pe eveniment cu netul
 *     calculat pe bilete.
 *  3. Anomalii pe toată baza (dubluri, evenimente fără decont, comenzi
 *     plătite fără bilete etc.).
 *  4. Totalurile de bani din perioada Tixello, de comparat cu contul.
 */
class BalancesAuditCommand extends Command
{
    protected $signature = 'balances:audit
        {--marketplace=1 : Marketplace client id}
        {--organizer= : Doar acest organizator (test rapid)}';

    protected $description = 'Audit „Sold disponibil”: refacere independentă, categorii, anomalii (doar citește).';

    private const TIXELLO_FROM = '2026-04-15';

    private array $anomalies = [];

    public function handle(SalesBreakdownService $service): int
    {
        $mp = (int) $this->option('marketplace');
        $onlyOrg = $this->option('organizer') ? (int) $this->option('organizer') : null;
        $now = now();
        $cutoff = Carbon::parse(self::TIXELLO_FROM)->endOfDay();
        $dir = (getenv('HOME') ?: storage_path('app')) . '/balances-audit';
        @mkdir($dir, 0775, true);
        $stamp = $now->format('Ymd-His');
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');

        $paidStatuses = SalesBreakdownService::PAID_ORDER_STATUSES;
        $offline = array_merge(SalesBreakdownService::POS_SOURCES, ['test_order', 'external_import', 'pos_test', 'legacy_import']);

        // ---------------- 0. cardul, așa cum e afișat ----------------
        $orgQ = MarketplaceOrganizer::where('marketplace_client_id', $mp)->when($onlyOrg, fn ($q) => $q->whereKey($onlyOrg));
        $card = (clone $orgQ)->selectRaw('COALESCE(SUM(available_balance),0) a, COALESCE(SUM(pending_balance),0) p')->toBase()->first();
        $organizers = (clone $orgQ)->get(['id', 'name', 'available_balance', 'pending_balance', 'total_paid_out'])->keyBy('id');

        // ---------------- 2a. comenzile Tixello online, independent ----------------
        $this->info('Comenzi Tixello: încasat − comision − extra, împărțit pe evenimente…');
        $indep = [];      // event_id => net independent (comenzi curate)
        $indepApprox = []; // event_id => partea din comenzi rambursate parțial / multi-eveniment
        DB::table('orders')
            ->where('marketplace_client_id', $mp)
            ->when($onlyOrg, fn ($q) => $q->where('marketplace_organizer_id', $onlyOrg))
            ->whereIn('status', $paidStatuses)
            ->whereNotNull('source')
            ->whereNotIn('source', $offline)
            ->select(['id', 'total', 'commission_amount', 'meta', 'processing_fee_cents', 'status'])
            ->orderBy('id')
            ->chunk(2000, function ($orders) use (&$indep, &$indepApprox) {
                $ids = $orders->pluck('id');
                $tk = DB::table('tickets')->whereIn('order_id', $ids)
                    ->selectRaw('order_id, COALESCE(event_id, marketplace_event_id) ev, status, COALESCE(price,0) price')
                    ->get()->groupBy('order_id');
                foreach ($orders as $o) {
                    $meta = json_decode((string) $o->meta, true) ?: [];
                    $extras = (float) ($meta['insurance_amount'] ?? 0) + (float) ($meta['cultural_card_surcharge'] ?? 0);
                    $net = (float) $o->total - (float) $o->commission_amount - $extras - ((int) $o->processing_fee_cents) / 100;
                    $tickets = $tk[$o->id] ?? collect();
                    if ($tickets->isEmpty()) {
                        continue; // raportat la anomalii
                    }
                    $byEv = $tickets->groupBy('ev');
                    $allValid = $tickets->every(fn ($t) => in_array($t->status, ['valid', 'used'], true));
                    $clean = $allValid && $byEv->count() === 1;
                    $sumAll = (float) $tickets->sum('price');
                    foreach ($byEv as $ev => $g) {
                        if (! $ev) {
                            continue;
                        }
                        $share = $sumAll > 0 ? (float) $g->sum('price') / $sumAll : $g->count() / max(1, $tickets->count());
                        $valid = $g->filter(fn ($t) => in_array($t->status, ['valid', 'used'], true));
                        $gp = (float) $g->sum('price');
                        $validShare = $gp > 0 ? (float) $valid->sum('price') / $gp : ($g->count() ? $valid->count() / $g->count() : 0);
                        $part = $net * $share * $validShare;
                        if ($clean) {
                            $indep[(int) $ev] = ($indep[(int) $ev] ?? 0) + $part;
                        } else {
                            $indepApprox[(int) $ev] = ($indepApprox[(int) $ev] ?? 0) + $part;
                        }
                    }
                }
            });

        // ---------------- 1. netul pe evenimente (calculul soldului) ----------------
        $events = Event::where('marketplace_client_id', $mp)->whereNotNull('marketplace_organizer_id')
            ->when($onlyOrg, fn ($q) => $q->where('marketplace_organizer_id', $onlyOrg))
            ->orderBy('id')->get();
        $payoutsByEvent = MarketplacePayout::where('marketplace_client_id', $mp)->whereNotNull('event_id')
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->get(['id', 'event_id', 'marketplace_organizer_id', 'status', 'amount'])->groupBy('event_id');

        $this->info('Netul pe ' . $events->count() . ' evenimente (durează)…');
        $bar = $this->output->createProgressBar($events->count());
        $evRows = [];
        $netByOrg = [];
        foreach ($events as $event) {
            $bar->advance();
            try {
                $bal = MarketplacePayout::balanceBreakdown($event, $service);
                $net = round((float) ($bal['total_net'] ?? 0), 2);
                $tix = null;
                if (isset($indep[$event->id]) || isset($indepApprox[$event->id])) {
                    $tix = $service->build($event, excludePos: true, excludeLegacyImport: true);
                }
            } catch (\Throwable $e) {
                $this->anomaly('eroare_calcul', $event, null, 0, mb_substr($e->getMessage(), 0, 120));

                continue;
            }
            $ps = $payoutsByEvent[$event->id] ?? collect();
            $done = (float) $ps->where('status', 'completed')->sum('amount');
            $flight = (float) $ps->whereIn('status', ['approved', 'processing'])->sum('amount');
            $draft = (float) $ps->where('status', 'pending')->sum('amount');
            $avail = round($net - $done - $flight, 2);
            $end = $event->getEffectiveEndDatetime();
            $bucket = ! $end ? 'fara_data_final' : ($end->gt($now) ? 'viitor' : ($end->lte($cutoff) ? 'vechi_pana_15_04' : ($end->gt($now->copy()->subDays(45)) ? 'incheiat_sub_45_zile' : 'incheiat_peste_45_zile')));
            $orgId = (int) $event->marketplace_organizer_id;
            $netByOrg[$orgId] = ($netByOrg[$orgId] ?? 0) + $net;

            $tixNet = $tix ? round((float) ($tix['total_net'] ?? 0), 2) : null;
            $indepNet = isset($indep[$event->id]) ? round($indep[$event->id], 2) : null;
            $approx = isset($indepApprox[$event->id]) ? round($indepApprox[$event->id], 2) : 0.0;
            $diff = $tixNet !== null ? round($tixNet - (($indepNet ?? 0) + $approx), 2) : null;

            $evRows[] = [
                'event_id' => $event->id, 'organizer_id' => $orgId, 'titlu' => $this->title($event),
                'final' => $end?->format('d.m.Y'), 'categorie' => $bucket,
                'net_sold' => $net, 'finalizat' => round($done, 2), 'aprobat' => round($flight, 2), 'ciorne_pending' => round($draft, 2),
                'disponibil' => $avail, 'net_tixello_bilete' => $tixNet, 'net_tixello_comenzi' => $indepNet, 'comenzi_aprox' => $approx,
                'diferenta_bilete_vs_comenzi' => $diff,
            ];

            if ($diff !== null && abs($diff) > 1) {
                $this->anomaly($approx != 0.0 ? 'net_diferit_aprox' : 'net_diferit', $event, $orgId, $diff,
                    "pe bilete {$tixNet}, din comenzi " . ($indepNet ?? 0) . ($approx != 0.0 ? " + aprox {$approx}" : ''));
            }
            if ($end && $end->lte($now->copy()->subDays(3)) && $net > 0.01 && $ps->isEmpty()) {
                $this->anomaly('incheiat_fara_decont', $event, $orgId, $net, 'eveniment încheiat ' . $end->format('d.m.Y') . ', niciun decont');
            }
            if ($bucket === 'vechi_pana_15_04' && abs($avail) > 1) {
                $this->anomaly('vechi_sold_nenul', $event, $orgId, $avail, 'eveniment vechi cu sold ≠ 0');
            }
            if ($avail < -1) {
                $this->anomaly('eveniment_platit_peste_net', $event, $orgId, $avail, "net {$net}, plătit/aprobat " . round($done + $flight, 2));
            }
            foreach ($ps->whereIn('status', ['approved', 'processing', 'completed'])->groupBy(fn ($p) => number_format((float) $p->amount, 2, '.', '')) as $amt => $same) {
                if ($same->count() > 1 && (float) $amt > 0) {
                    $this->anomaly('decont_dublu', $event, $orgId, (float) $amt, 'deconturi ' . $same->pluck('id')->map(fn ($i) => "#{$i}")->implode(', ') . ' cu aceeași sumă');
                }
            }
            foreach ($ps as $p) {
                if ((int) $p->marketplace_organizer_id !== $orgId && $p->status !== 'pending') {
                    $this->anomaly('decont_alt_organizator', $event, $orgId, (float) $p->amount, "#{$p->id} e pe organizatorul {$p->marketplace_organizer_id}");
                }
            }
        }
        $bar->finish();
        $this->newLine(2);

        // ---------------- refacerea cardului pe organizatori ----------------
        $orgRows = [];
        $sumDerived = 0.0;
        foreach ($organizers as $org) {
            $paid = (float) MarketplacePayout::where('marketplace_organizer_id', $org->id)->where('status', 'completed')->sum('amount')
                - MarketplacePayout::advanceOffsetForOrganizer($org->id, ['completed']);
            $pend = (float) MarketplacePayout::where('marketplace_organizer_id', $org->id)->whereIn('status', ['approved', 'processing'])->sum('amount')
                - MarketplacePayout::advanceOffsetForOrganizer($org->id, ['approved', 'processing']);
            $net = round($netByOrg[$org->id] ?? 0, 2);
            $derived = round($net - $paid - $pend, 2);
            $sumDerived += $derived;
            $evSum = round(collect($evRows)->where('organizer_id', $org->id)->sum('disponibil'), 2);
            $orgLevel = round($derived - $evSum, 2);
            $completedSum = round((float) MarketplacePayout::where('marketplace_organizer_id', $org->id)->where('status', 'completed')->sum('amount'), 2);
            if (abs($derived - (float) $org->available_balance) > 1 || abs(round($pend, 2) - (float) $org->pending_balance) > 1) {
                $this->anomaly('sold_salvat_diferit', null, $org->id, round((float) $org->available_balance - $derived, 2), "salvat {$org->available_balance}/{$org->pending_balance}, refăcut {$derived}/" . round($pend, 2));
            }
            if (abs($completedSum - (float) $org->total_paid_out) > 1) {
                $this->anomaly('total_platit_coloana', null, $org->id, round((float) $org->total_paid_out - $completedSum, 2), "coloana {$org->total_paid_out}, Σ finalizate {$completedSum}");
            }
            if (abs($orgLevel) > 1) {
                $this->anomaly('sold_la_nivel_organizator', null, $org->id, $orgLevel, 'avansuri / deconturi fără eveniment / pe evenimente ale altui organizator');
            }
            if (abs($derived) > 0.004 || abs($orgLevel) > 0.004) {
                $orgRows[$org->id] = ['derived' => $derived, 'org_level' => $orgLevel];
            }
        }

        // ---------------- 3. anomalii pe comenzi ----------------
        $this->info('Anomalii pe comenzi…');
        $base = fn () => DB::table('orders as o')->where('o.marketplace_client_id', $mp)->when($onlyOrg, fn ($q) => $q->where('o.marketplace_organizer_id', $onlyOrg));

        $noTickets = $base()->whereIn('o.status', $paidStatuses)
            ->whereNotExists(fn ($q) => $q->from('tickets as t')->whereColumn('t.order_id', 'o.id')->whereIn('t.status', ['valid', 'used']))
            ->selectRaw("COALESCE(o.source,'(fara sursa)') src, count(*) n, COALESCE(sum(o.total),0) s")->groupBy('o.source')->get();
        $ghost = $base()->whereNotIn('o.status', $paidStatuses)
            ->join('tickets as t', 't.order_id', '=', 'o.id')->whereIn('t.status', ['valid', 'used'])
            ->selectRaw("COALESCE(o.source,'(fara sursa)') src, o.status st, count(*) n, COALESCE(sum(t.price),0) s")->groupBy('o.source', 'o.status')->get();
        $refundedValid = DB::table('marketplace_refund_items as ri')->join('tickets as t', 't.id', '=', 'ri.ticket_id')
            ->join('orders as o', 'o.id', '=', 't.order_id')->where('o.marketplace_client_id', $mp)
            ->when($onlyOrg, fn ($q) => $q->where('o.marketplace_organizer_id', $onlyOrg))
            ->where('ri.status', 'refunded')->whereIn('t.status', ['valid', 'used'])->whereIn('o.status', $paidStatuses)
            ->selectRaw('count(*) n, COALESCE(sum(ri.refund_amount),0) s')->first();
        $nullSource = $base()->whereIn('o.status', $paidStatuses)->whereNull('o.source')
            ->join('tickets as t', 't.order_id', '=', 'o.id')->whereIn('t.status', ['valid', 'used'])
            ->selectRaw('count(distinct o.id) n, count(*) b, COALESCE(sum(t.price),0) s')->first();
        $sources = $base()->whereIn('o.status', $paidStatuses)
            ->selectRaw("COALESCE(o.source,'(fara sursa)') src, count(*) n, COALESCE(sum(o.total),0) s")->groupBy('o.source')->orderByDesc('s')->get();

        // ---------------- 4. bani, perioada Tixello ----------------
        $from = Carbon::parse(self::TIXELLO_FROM)->startOfDay();
        $cash = $base()->where('o.created_at', '>=', $from)->whereNotNull('o.source')->whereNotIn('o.source', $offline)
            ->whereIn('o.status', array_merge($paidStatuses, ['refunded']))
            ->selectRaw('COALESCE(sum(o.total),0) incasat, COALESCE(sum(o.commission_amount),0) comision, count(*) n')->first();
        $refunded = DB::table('marketplace_refund_items as ri')->join('tickets as t', 't.id', '=', 'ri.ticket_id')
            ->join('orders as o', 'o.id', '=', 't.order_id')->where('o.marketplace_client_id', $mp)->where('ri.status', 'refunded')
            ->when($onlyOrg, fn ($q) => $q->where('o.marketplace_organizer_id', $onlyOrg))
            ->where('o.created_at', '>=', $from)->whereNotIn('o.source', $offline)
            ->selectRaw('COALESCE(sum(ri.refund_amount),0) s, COALESCE(sum(case when ri.commission_refunded then ri.commission_amount else 0 end),0) c')->first();
        $paidOut = (float) MarketplacePayout::where('marketplace_client_id', $mp)->where('status', 'completed')
            ->when($onlyOrg, fn ($q) => $q->where('marketplace_organizer_id', $onlyOrg))
            ->where('completed_at', '>=', $from)->where(fn ($q) => $q->whereNull('payment_notes')->orWhere('payment_notes', '!=', \App\Services\Marketplace\LegacySettlementService::PAID_NOTE))->sum('amount');

        // ---------------- fișiere ----------------
        $evPath = "{$dir}/evenimente-{$stamp}.csv";
        $this->csv($evPath, array_keys($evRows[0] ?? ['event_id' => 0]), $evRows);
        $anPath = "{$dir}/anomalii-{$stamp}.csv";
        $this->csv($anPath, ['tip', 'event_id', 'eveniment', 'organizer_id', 'organizator', 'suma', 'detalii'], array_map(function ($a) use ($organizers) {
            $a['organizator'] = (string) ($a['organizer_id'] ? ($organizers->get($a['organizer_id'])?->name ?? '') : '');

            return [$a['tip'], $a['event_id'], $a['eveniment'], $a['organizer_id'], $a['organizator'], $a['suma'], $a['detalii']];
        }, $this->anomalies));

        // ---------------- raport ----------------
        $ev = collect($evRows);
        $orgLevelSum = round(collect($orgRows)->sum('org_level'), 2);
        $this->line('<options=bold>1. CARDUL</>');
        $this->table(['', 'lei'], [
            ['Sold disponibil afișat (Σ salvat)', $fmt($card->a)],
            ['Refăcut acum din vânzări și deconturi', $fmt($sumDerived)],
            ['Diferență', $fmt($card->a - $sumDerived)],
            ['În procesare afișat', $fmt($card->p)],
        ]);
        $this->line('<options=bold>Refăcut, pe categorii (suma lor = refăcut)</>');
        $cat = [];
        foreach (['viitor', 'fara_data_final', 'incheiat_sub_45_zile', 'incheiat_peste_45_zile', 'vechi_pana_15_04'] as $b) {
            $g = $ev->where('categorie', $b);
            $cat[] = [$b, $g->where('disponibil', '>', 0.004)->count(), $fmt($g->where('disponibil', '>', 0.004)->sum('disponibil')),
                $g->where('disponibil', '<', -0.004)->count(), $fmt($g->where('disponibil', '<', -0.004)->sum('disponibil')), $fmt($g->sum('disponibil'))];
        }
        $cat[] = ['nivel organizator (avans, fără eveniment, alt org.)', '', '', '', '', $fmt($orgLevelSum)];
        $cat[] = ['TOTAL', '', '', '', '', $fmt($ev->sum('disponibil') + $orgLevelSum)];
        $this->table(['Categorie', 'Ev. +', 'Plus', 'Ev. −', 'Minus', 'Total'], $cat);

        $this->line('<options=bold>2. NET TIXELLO: pe bilete vs din comenzi (încasat − comision − extra)</>');
        $cmp = $ev->whereNotNull('net_tixello_bilete');
        $this->table(['', ''], [
            ['Evenimente comparate', $cmp->count()],
            ['Net pe bilete (calculul soldului)', $fmt($cmp->sum('net_tixello_bilete'))],
            ['Net din comenzi curate', $fmt($cmp->sum('net_tixello_comenzi'))],
            ['+ comenzi rambursate parțial / multi-eveniment (aprox.)', $fmt($cmp->sum('comenzi_aprox'))],
            ['Diferență totală', $fmt($cmp->sum('diferenta_bilete_vs_comenzi'))],
            ['Evenimente cu diferență > 1 leu', $cmp->filter(fn ($r) => abs((float) $r['diferenta_bilete_vs_comenzi']) > 1)->count()],
        ]);

        $this->line('<options=bold>3. ANOMALII</>');
        $this->table(['Tip', 'Câte', 'Sumă'], collect($this->anomalies)->groupBy('tip')
            ->map(fn ($g, $t) => [$t, $g->count(), $fmt($g->sum('suma'))])->values()->all());
        $this->table(['Comenzi plătite FĂRĂ bilete valide (sursă)', 'Comenzi', 'Total'], $noTickets->map(fn ($r) => [$r->src, $r->n, $fmt($r->s)])->all());
        $this->table(['Comenzi NEplătite cu bilete valide (sursă/status)', 'Bilete', 'Valoare'], $ghost->map(fn ($r) => [$r->src . ' / ' . $r->st, $r->n, $fmt($r->s)])->all());
        $this->table(['', 'Câte', 'Sumă'], [
            ['Bilete rambursate dar încă valide', $refundedValid->n, $fmt($refundedValid->s)],
            ['Bilete valide în comenzi plătite FĂRĂ sursă (nu intră în sold)', $nullSource->b . ' (' . $nullSource->n . ' com.)', $fmt($nullSource->s)],
        ]);
        $this->table(['Surse comenzi plătite', 'Comenzi', 'Total'], $sources->map(fn ($r) => [$r->src, $r->n, $fmt($r->s)])->all());

        $this->line('<options=bold>4. BANI, comenzi online din ' . $from->format('d.m.Y') . '</>');
        $this->table(['', 'lei'], [
            ['Încasat de la clienți (' . $cash->n . ' comenzi, incl. rambursate)', $fmt($cash->incasat)],
            ['Rambursat clienților (bilete)', $fmt($refunded->s)],
            ['Comision reținut la checkout', $fmt($cash->comision)],
            ['Deconturi plătite real din ' . $from->format('d.m.Y'), $fmt($paidOut)],
        ]);

        $this->info("CSV evenimente: {$evPath}");
        $this->info("CSV anomalii:   {$anPath}");

        return self::SUCCESS;
    }

    private function anomaly(string $type, ?Event $event, ?int $orgId, float $amount, string $details): void
    {
        $this->anomalies[] = [
            'tip' => $type,
            'event_id' => $event?->id,
            'eveniment' => $event ? $this->title($event) : '',
            'organizer_id' => $orgId ?? ($event ? (int) $event->marketplace_organizer_id : null),
            'suma' => round($amount, 2),
            'detalii' => $details,
        ];
    }

    private function title(Event $event): string
    {
        $t = $event->title;
        if (is_array($t)) {
            $t = $t['ro'] ?? $t['en'] ?? (reset($t) ?: '');
        }

        return mb_substr((string) $t, 0, 60);
    }

    private function csv(string $path, array $head, array $rows): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $head, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, array_values($r), ',', '"', '\\');
        }
        fclose($fh);
    }
}

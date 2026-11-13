<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\TicketType;
use App\Services\Marketplace\SalesBreakdownService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Prețul biletelor din comenzile importate din WordPress (orders.source =
 * legacy_import) adus la ce a plătit efectiv clientul:
 *  A — pe bilet e prețul plătit, cu tot cu comisionul peste preț, iar calculul
 *      mai adaugă o dată comisionul;
 *  B — pe bilet e prețul de catalog, mai mare decât ce s-a plătit (tranșe,
 *      promoții pierdute la import).
 *
 * Se schimbă doar tickets.price, doar pe comenzile legacy_import. Pe fiecare
 * comandă prețurile scad proporțional, ca preț + comision peste preț = totalul
 * plătit. Comenzile cu reducere, bilete anulate, mai multe evenimente sau
 * prețuri diferite pe același tip se sar.
 *
 * Implicit doar simulează: pune prețurile noi într-o tranzacție, rulează
 * calculul real (SalesBreakdownService) înainte și după, apoi anulează.
 * Cu --apply păstrează doar evenimentele la care diferența reală = cea
 * estimată; prețurile vechi rămân în legacy_ticket_price_fixes (--revert=lot).
 */
class FixLegacyTicketPrices extends Command
{
    protected $signature = 'tickets:fix-legacy-prices
        {--event=* : Doar aceste evenimente}
        {--apply : Scrie prețurile (fără, doar simulare)}
        {--revert= : Pune înapoi prețurile unui lot aplicat}
        {--dir= : Folderul rapoartelor CSV (implicit ~/legacy-ticket-prices)}';

    protected $description = 'Comenzile importate din WordPress: prețul biletelor adus la ce a plătit clientul (cazurile A și B).';

    private const TABLE = 'legacy_ticket_price_fixes';

    private const PAID = ['paid', 'confirmed', 'completed'];

    private const VALID = ['valid', 'used'];

    private const ON_TOP = ['on_top', 'added_on_top'];

    public function handle(SalesBreakdownService $service): int
    {
        if ($batch = $this->option('revert')) {
            return $this->revert((string) $batch);
        }

        $apply = (bool) $this->option('apply');
        if ($apply && ! Schema::hasTable(self::TABLE)) {
            $this->error('Lipsește tabelul ' . self::TABLE . ' — rulează întâi php artisan migrate.');

            return self::FAILURE;
        }

        $q = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.source', 'legacy_import')
            ->whereIn('o.status', self::PAID)
            ->whereIn('t.status', self::VALID)
            ->whereNotNull('t.event_id');
        if ($only = array_filter(array_map('intval', (array) $this->option('event')))) {
            $q->whereIn('t.event_id', $only);
        }
        $eventIds = $q->distinct()->orderBy('t.event_id')->pluck('t.event_id');

        if ($apply && ! $this->confirm('Scriu prețurile noi pe ' . $eventIds->count() . ' evenimente verificate (doar comenzile importate)?', false)) {
            return self::FAILURE;
        }

        $batch = $apply ? (string) Str::uuid() : null;
        $dir = $this->option('dir') ?: ((getenv('HOME') ?: storage_path('app')) . '/legacy-ticket-prices');
        @mkdir($dir, 0775, true);
        $stamp = now()->format('Ymd-His') . ($apply ? '-aplicat' : '-simulare');
        $ordersCsv = $this->csv("{$dir}/comenzi-{$stamp}.csv", ['event_id', 'eveniment', 'organizer_id', 'comanda', 'caz', 'platit',
            'bilete_vechi', 'bilete_noi', 'comision_vechi', 'comision_nou', 'rest_rotunjire', 'rezultat']);
        $eventsCsv = $this->csv("{$dir}/evenimente-{$stamp}.csv", ['event_id', 'eveniment', 'organizer_id', 'organizator', 'comenzi_A', 'comenzi_B',
            'comenzi_sarite', 'bilete_modificate', 'net_inainte', 'net_dupa', 'diferenta_net', 'estimat_net', 'comision_inainte',
            'comision_dupa', 'diferenta_comision', 'estimat_comision', 'verificare', 'aplicat']);

        $sum = ['events' => 0, 'A' => 0, 'B' => 0, 'tickets' => 0, 'net' => 0.0, 'comm' => 0.0, 'failed' => 0, 'applied' => 0];
        $skips = [];
        $sources = [];
        $organizers = [];

        $bar = $this->output->createProgressBar($eventIds->count());
        foreach ($eventIds as $eventId) {
            $bar->advance();
            $event = Event::with(['marketplaceOrganizer', 'marketplaceClient'])->find($eventId);
            if (! $event || ($event->display_template ?? null) === 'leisure_venue') {
                continue;
            }

            $plan = $this->plan($event);
            foreach ($plan['rows'] as $r) {
                fputcsv($ordersCsv, $r, ',', '"', '\\');
                if (! str_starts_with($r[11], 'de modificat')) {
                    $reason = trim(explode(':', $r[11], 2)[1] ?? $r[11]);
                    $skips[$reason] = ($skips[$reason] ?? 0) + 1;
                }
            }
            if (empty($plan['changes'])) {
                continue;
            }

            $sum['events']++;
            $sum['A'] += $plan['count']['A'];
            $sum['B'] += $plan['count']['B'];
            foreach ($plan['sources'] as $src => $n) {
                $sources[$src] = ($sources[$src] ?? 0) + $n;
            }

            [$before, $after, $ok, $applied] = $this->verify($event, $plan, $service, $batch);

            $netDiff = round($after['net'] - $before['net'], 2);
            $commDiff = round($after['comm'] - $before['comm'], 2);
            $sum['tickets'] += count($plan['changes']);
            $sum['net'] += $netDiff;
            $sum['comm'] += $commDiff;
            $sum['failed'] += $ok ? 0 : 1;
            $sum['applied'] += $applied ? 1 : 0;
            if ($applied && $event->marketplace_organizer_id) {
                $organizers[(int) $event->marketplace_organizer_id] = true;
            }

            fputcsv($eventsCsv, [$event->id, $this->title($event), $event->marketplace_organizer_id, $event->marketplaceOrganizer?->name,
                $plan['count']['A'], $plan['count']['B'], $plan['count']['skipped'], count($plan['changes']),
                $before['net'], $after['net'], $netDiff, round($plan['net_delta'], 2),
                $before['comm'], $after['comm'], $commDiff, round($plan['comm_delta'], 2),
                $ok ? 'ok' : 'NU', $applied ? 'da' : 'nu'], ',', '"', '\\');
        }
        $bar->finish();
        fclose($ordersCsv);
        fclose($eventsCsv);
        $this->newLine(2);

        foreach (array_keys($organizers) as $orgId) {
            MarketplaceOrganizer::find($orgId)?->recomputeBalances($service);
        }

        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $this->table(['', ''], [
            ['Evenimente cu bilete de corectat', $sum['events']],
            ['Comenzi caz A (comision dublat)', $sum['A']],
            ['Comenzi caz B (preț peste ce s-a plătit)', $sum['B']],
            ['Bilete cu preț nou', $sum['tickets']],
            ['Net organizatori: diferență totală', $fmt($sum['net'])],
            ['Comision Ambilet: diferență totală', $fmt($sum['comm'])],
            ['Evenimente cu verificare eșuată (nu se scriu)', $sum['failed']],
            ['Bilete modificate pe sursa comenzii', collect($sources)->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ') ?: '—'],
        ]);
        if ($skips) {
            arsort($skips);
            $this->table(['Comenzi A/B sărite — motiv', 'Comenzi'], collect($skips)->map(fn ($n, $r) => [$r, $n])->values()->all());
        }
        $this->info("Rapoarte: {$dir}/evenimente-{$stamp}.csv și comenzi-{$stamp}.csv");
        if ($apply) {
            $this->info("Aplicat pe {$sum['applied']} evenimente. Lot: {$batch} (anulare: --revert={$batch}). Soldurile a " . count($organizers) . ' organizatori recalculate.');
        } else {
            $this->comment('Simulare — nu s-a scris nimic.');
        }

        return self::SUCCESS;
    }

    /**
     * Comenzile importate ale evenimentului, clasificate, și prețurile noi
     * pentru cazurile A/B. Copiază regulile SalesBreakdownService (preț pe
     * tip = primul bilet, rata/modul implicite, comision minim).
     */
    private function plan(Event $event): array
    {
        $rate = (float) ($event->commission_rate
            ?? $event->marketplaceOrganizer?->commission_rate
            ?? $event->tenant?->commission_rate
            ?? $event->marketplaceClient?->commission_rate
            ?? 5);
        $mode = $event->commission_mode
            ?? $event->marketplaceOrganizer?->default_commission_mode
            ?? $event->marketplaceClient?->commission_mode
            ?? 'included';
        $floor = $event->marketplaceOrganizer?->commission_use_floor
            ? (float) ($event->marketplaceOrganizer?->fixed_commission_default ?? 0)
            : 0.0;

        $orderIds = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('t.event_id', $event->id)
            ->where('o.source', 'legacy_import')
            ->whereIn('o.status', self::PAID)
            ->distinct()
            ->pluck('o.id');

        $types = TicketType::where('event_id', $event->id)->get()->keyBy('id');
        $title = $this->title($event);
        $out = ['rows' => [], 'changes' => [], 'net_delta' => 0.0, 'comm_delta' => 0.0, 'sources' => [], 'count' => ['A' => 0, 'B' => 0, 'skipped' => 0]];

        foreach ($orderIds->chunk(2000) as $chunk) {
            $orders = DB::table('orders')->whereIn('id', $chunk)->get(['id', 'total', 'discount_amount', 'source']);
            $tickets = DB::table('tickets')->whereIn('order_id', $chunk)
                ->get(['id', 'order_id', 'event_id', 'ticket_type_id', 'status', 'price'])
                ->groupBy('order_id');

            foreach ($orders as $o) {
                $r = $this->planOrder($o, $tickets[$o->id] ?? collect(), $types, $rate, $mode, $floor);
                if (! $r) {
                    continue;
                }

                $row = [$event->id, $title, $event->marketplace_organizer_id, $o->id, $r['case'], round((float) $o->total, 2),
                    $r['old_label'], $r['new_label'] ?? '', $r['old_comm'], $r['new_comm'] ?? '', $r['residual'] ?? ''];

                if (isset($r['skip'])) {
                    $out['count']['skipped']++;
                    $out['rows'][] = array_merge($row, ['sărită: ' . $r['skip']]);

                    continue;
                }

                $out['count'][$r['case']]++;
                $out['rows'][] = array_merge($row, ['de modificat']);
                $out['net_delta'] += $r['net_delta'];
                $out['comm_delta'] += $r['comm_delta'];
                foreach ($r['changes'] as $c) {
                    $out['changes'][] = $c + ['order_id' => $o->id, 'event_id' => $event->id, 'category' => $r['case']];
                }
                $out['sources'][$o->source] = ($out['sources'][$o->source] ?? 0) + count($r['changes']);
            }
        }

        return $out;
    }

    /** null = comanda e corectă sau nu e caz A/B. */
    private function planOrder(object $o, Collection $all, Collection $types, float $rate, string $mode, float $floor): ?array
    {
        $valid = $all->whereIn('status', self::VALID);
        if ($valid->isEmpty()) {
            return null;
        }

        $total = round((float) $o->total, 2);
        $lines = [];
        $skip = null;
        foreach ($valid->groupBy('ticket_type_id') as $ttId => $g) {
            $tt = $types[$ttId] ?? null;
            if (! $tt) {
                $skip ??= 'tip de bilet de pe alt eveniment';

                continue;
            }
            if ($g->pluck('price')->map(fn ($p) => $p === null ? 'null' : number_format((float) $p, 2, '.', ''))->unique()->count() > 1) {
                $skip ??= 'prețuri diferite pe același tip de bilet';
            }
            $raw = $g->first()->price;
            $cur = $raw !== null && (float) $raw > 0
                ? (float) $raw
                : ((int) ($tt->sale_price_cents ?? 0) > 0 ? $tt->sale_price_cents : (int) ($tt->price_cents ?? 0)) / 100;

            $ef = $tt->getEffectiveCommission($rate, $mode);
            $onTop = in_array($ef['mode'], self::ON_TOP, true);
            $lines[] = [
                'tt' => $tt,
                'ids' => $g->pluck('id')->all(),
                'raw' => $raw,
                'n' => $g->count(),
                'cur' => round($cur, 2),
                'on_top' => $onTop,
                'a' => in_array($ef['type'], ['percentage', 'both'], true) ? (float) $ef['rate'] / 100 : 0.0,
                'f' => in_array($ef['type'], ['fixed', 'both'], true) ? (float) $ef['fixed'] : 0.0,
            ];
        }
        if (! $lines) {
            return null;
        }

        $comm = fn (array $l, float $p) => $this->commission($l['tt'], $p, $rate, $mode, $floor);
        // $lines se primește ca argument: arrow fn-urile copiază variabilele la
        // definire și n-ar vedea prețurile noi adăugate mai jos.
        $paidFor = fn (array $lines, string $key) => array_sum(array_map(fn ($l) => $l['n'] * ($l[$key] + ($l['on_top'] ? $comm($l, $l[$key]) : 0)), $lines));
        $commFor = fn (array $lines, string $key) => array_sum(array_map(fn ($l) => $l['n'] * $comm($l, $l[$key]), $lines));
        $label = fn (array $lines, string $key) => implode(' + ', array_map(fn ($l) => $l['n'] . 'x' . number_format($l[$key], 2, '.', ''), $lines));

        $expected = round($paidFor($lines, 'cur'), 2);
        $priceSum = array_sum(array_map(fn ($l) => $l['n'] * $l['cur'], $lines));
        $anyOnTop = collect($lines)->contains('on_top', true);

        if ($total < 0.01 || abs($total - $expected) < 0.5) {
            return null;
        }
        if ($anyOnTop && abs($total - $priceSum) < 0.5) {
            $case = 'A';
        } elseif ($total < $expected) {
            $case = 'B';
        } else {
            return null;
        }

        $base = ['case' => $case, 'old_label' => $label($lines, 'cur'), 'old_comm' => round($commFor($lines, 'cur'), 2)];
        $skip ??= match (true) {
            $valid->pluck('event_id')->unique()->count() > 1 => 'bilete pe mai multe evenimente',
            $all->count() > $valid->count() => 'are bilete anulate sau rambursate',
            (float) ($o->discount_amount ?? 0) > 0.004 => 'reducere pe comandă',
            default => null,
        };
        if ($skip) {
            return $base + ['skip' => $skip];
        }

        // Preț + comision peste preț = a·p + f, liniar; toate prețurile se
        // scalează cu același k, ca proporția dintre bilete să rămână.
        $fixedOnTop = array_sum(array_map(fn ($l) => $l['on_top'] ? $l['n'] * $l['f'] : 0, $lines));
        $den = array_sum(array_map(fn ($l) => $l['n'] * $l['cur'] * (1 + ($l['on_top'] ? $l['a'] : 0)), $lines));
        $k = $den > 0 ? ($total - $fixedOnTop) / $den : 0;
        if ($k <= 0 || $k >= 1) {
            return $base + ['skip' => 'prețul nou nu se poate calcula'];
        }
        foreach ($lines as &$l) {
            $l['new'] = round($l['cur'] * $k, 2);
        }
        unset($l);

        $residual = round($paidFor($lines, 'new') - $total, 2);
        $tickets = array_sum(array_column($lines, 'n'));
        $base += ['new_label' => $label($lines, 'new'), 'new_comm' => round($commFor($lines, 'new'), 2), 'residual' => $residual];
        if (abs($residual) > 0.01 * $tickets + 0.01) {
            return $base + ['skip' => 'nu iese exact totalul plătit (comision minim sau comisioane diferite)'];
        }
        if (collect($lines)->contains(fn ($l) => $l['new'] <= 0 || $l['new'] > $l['cur'])) {
            return $base + ['skip' => 'prețul nou ar fi 0 sau mai mare'];
        }

        $netOf = fn (array $l, float $p) => $l['on_top'] ? $p : $p - $comm($l, $p);
        $changes = [];
        $netDelta = 0.0;
        $commDelta = 0.0;
        foreach ($lines as $l) {
            $netDelta += $l['n'] * ($netOf($l, $l['new']) - $netOf($l, $l['cur']));
            $commDelta += $l['n'] * ($comm($l, $l['new']) - $comm($l, $l['cur']));
            foreach ($l['ids'] as $id) {
                $changes[] = ['ticket_id' => $id, 'old' => $l['raw'] === null ? null : round((float) $l['raw'], 2), 'new' => $l['new']];
            }
        }

        return $base + ['changes' => $changes, 'net_delta' => $netDelta, 'comm_delta' => $commDelta];
    }

    /** Ca în SalesBreakdownService: comisionul tipului de bilet, cu minimul organizatorului. */
    private function commission(TicketType $tt, float $price, float $rate, string $mode, float $floor): float
    {
        $c = (float) $tt->calculateCommission($price, $rate, $mode);
        if ($floor > 0 && $price > 0 && $tt->allowsCommissionFloor() && $c < $floor) {
            $c = $floor;
        }

        return $c;
    }

    /**
     * Pune prețurile noi în tranzacție și compară calculul real înainte/după
     * cu estimarea. Păstrează doar cu --apply și doar dacă se potrivesc.
     */
    private function verify(Event $event, array $plan, SalesBreakdownService $service, ?string $batch): array
    {
        $read = function () use ($event, $service) {
            $b = $service->build($event, excludePos: true);

            return ['net' => round((float) ($b['total_net'] ?? 0), 2), 'comm' => round((float) ($b['total_commission'] ?? 0), 2)];
        };

        $before = $read();
        DB::beginTransaction();
        try {
            foreach (collect($plan['changes'])->groupBy(fn ($c) => number_format($c['new'], 2, '.', '')) as $price => $rows) {
                foreach ($rows->pluck('ticket_id')->chunk(1000) as $ids) {
                    DB::table('tickets')->whereIn('id', $ids->all())->update(['price' => (float) $price]);
                }
            }
            $after = $read();

            $tolerance = 0.01 * count($plan['changes']) + 0.05;
            $ok = abs(($after['net'] - $before['net']) - $plan['net_delta']) <= $tolerance
                && abs(($after['comm'] - $before['comm']) - $plan['comm_delta']) <= $tolerance;

            if ($batch && $ok) {
                $now = now();
                foreach (array_chunk($plan['changes'], 1000) as $chunk) {
                    DB::table(self::TABLE)->insert(array_map(fn ($c) => [
                        'batch' => $batch,
                        'ticket_id' => $c['ticket_id'],
                        'order_id' => $c['order_id'],
                        'event_id' => $c['event_id'],
                        'category' => $c['category'],
                        'old_price' => $c['old'],
                        'new_price' => $c['new'],
                        'created_at' => $now,
                    ], $chunk));
                }
                DB::commit();

                return [$before, $after, true, true];
            }

            DB::rollBack();

            return [$before, $after, $ok, false];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function revert(string $batch): int
    {
        if (! Schema::hasTable(self::TABLE)) {
            $this->error('Nu există tabelul ' . self::TABLE . '.');

            return self::FAILURE;
        }
        $rows = DB::table(self::TABLE)->where('batch', $batch)->whereNull('reverted_at')->get();
        if ($rows->isEmpty()) {
            $this->warn('Nimic de pus înapoi pentru lotul ' . $batch . '.');

            return self::SUCCESS;
        }
        if (! $this->confirm('Pun înapoi prețurile vechi pe ' . $rows->count() . ' bilete (' . $rows->pluck('event_id')->unique()->count() . ' evenimente)?', false)) {
            return self::FAILURE;
        }

        $restored = 0;
        $changed = 0;
        DB::transaction(function () use ($rows, &$restored, &$changed) {
            foreach ($rows as $r) {
                // Doar dacă prețul n-a mai fost schimbat între timp.
                $n = DB::table('tickets')->where('id', $r->ticket_id)->where('price', $r->new_price)->update(['price' => $r->old_price]);
                $n ? $restored++ : $changed++;
                DB::table(self::TABLE)->where('id', $r->id)->update(['reverted_at' => now()]);
            }
        });

        $orgIds = Event::whereIn('id', $rows->pluck('event_id')->unique())->pluck('marketplace_organizer_id')->filter()->unique();
        $service = app(SalesBreakdownService::class);
        foreach ($orgIds as $orgId) {
            MarketplaceOrganizer::find($orgId)?->recomputeBalances($service);
        }

        $this->info("Puse înapoi: {$restored} bilete. Sărite (preț schimbat între timp): {$changed}. Solduri recalculate: {$orgIds->count()} organizatori.");

        return self::SUCCESS;
    }

    /** @return resource */
    private function csv(string $path, array $header)
    {
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '\\');

        return $fh;
    }

    private function title(Event $event): string
    {
        $t = $event->title;
        if (is_string($t) && str_starts_with(trim($t), '{')) {
            $t = json_decode($t, true) ?: $t;
        }
        if (is_array($t)) {
            $t = $t['ro'] ?? $t['en'] ?? (collect($t)->first() ?: null);
        }

        return $t ?: ('Eveniment #' . $event->id);
    }
}

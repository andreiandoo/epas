<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use App\Services\Marketplace\LegacySettlementService;
use Illuminate\Console\Command;

/**
 * Soldurile din trecut, din linia de comandă — aceeași logică ca butoanele din
 * Balanțe (LegacySettlementService). Implicit doar analizează și scrie CSV;
 * scrie în DB doar cu --apply.
 */
class SettleLegacyPayouts extends Command
{
    protected $signature = 'payouts:settle-legacy
        {--organizer= : Doar acest organizator}
        {--csv= : Calea raportului CSV (implicit ~/settle-legacy/analiza-DATA.csv)}
        {--apply : Aprobă ciornele organizatorilor cu verdict OK}
        {--include-warn : Cu --apply, aprobă și organizatorii cu verdict Atenție}
        {--revert-organizer= : Anulează ultima aprobare a acestui organizator}
        {--realign : Ciornele vechi aprobate, aduse la netul corectat al evenimentului (cu --apply scrie)}
        {--realign-all : Cu --realign, pe toate evenimentele, nu doar pe cele cu prețuri de bilet corectate}
        {--revert-realign= : Anulează un lot de realiniere}
        {--finalize : Ciornele vechi aprobate → Finalizat, „plătit în sistemul anterior” (cu --apply scrie)}
        {--revert-finalize= : Anulează un lot de finalizare}
        {--transition : Ciornele din tranziție (evenimente încheiate după data limită, cu vânzări și din sistemul anterior) în loc de cele vechi}
        {--invitations : Invitațiile din sistemul vechi (bilete de 0 lei) scoase din net + deconturile noastre corectate (cu --apply scrie și pornește calculul nou)}
        {--revert-invitations= : Anulează un lot de corectare a invitațiilor}';

    protected $description = 'Ciornele vechi AmBilet: analizează / aprobă ciornele „În așteptare” pe evenimentele încheiate până la ' . LegacySettlementService::CUTOFF_DATE . '.';

    public function handle(LegacySettlementService $service): int
    {
        if ($id = $this->option('revert-organizer')) {
            $org = MarketplaceOrganizer::findOrFail((int) $id);
            if (! $this->confirm("Anulez ultima aprobare pentru {$org->name}?", false)) {
                return self::FAILURE;
            }
            $this->info('Readuse: ' . $service->revert($org, null, (bool) $this->option('transition')) . ' ciorne.');

            return self::SUCCESS;
        }

        if ($batch = $this->option('revert-realign')) {
            if (! $this->confirm("Anulez lotul de realiniere {$batch}?", false)) {
                return self::FAILURE;
            }
            $this->info('Ciorne readuse la suma dinainte: ' . $service->revertRealign((string) $batch) . '.');

            return self::SUCCESS;
        }

        if ($this->option('realign')) {
            return $this->realign($service);
        }

        if ($batch = $this->option('revert-finalize')) {
            if (! $this->confirm("Anulez lotul de finalizare {$batch} (deconturile revin în „Aprobat”)?", false)) {
                return self::FAILURE;
            }
            $this->info('Deconturi readuse în „Aprobat”: ' . $service->revertFinalize((string) $batch) . '.');

            return self::SUCCESS;
        }

        if ($this->option('finalize')) {
            return $this->finalize($service);
        }

        if ($batch = $this->option('revert-invitations')) {
            if (! $this->confirm("Anulez lotul {$batch} (sumele revin, calculul vechi repornește)?", false)) {
                return self::FAILURE;
            }
            $this->info('Deconturi readuse: ' . $service->revertInvitations((string) $batch) . '.');

            return self::SUCCESS;
        }

        if ($this->option('invitations')) {
            return $this->invitations($service);
        }

        $transition = (bool) $this->option('transition');
        $ids = $this->option('organizer')
            ? [(int) $this->option('organizer')]
            : ($transition
                ? LegacySettlementService::transitionCandidateIds(LegacySettlementService::MARKETPLACE_ID)
                : LegacySettlementService::candidateOrganizerIds(LegacySettlementService::MARKETPLACE_ID));

        $csvPath = $this->option('csv') ?: ((getenv('HOME') ?: storage_path('app')) . '/settle-legacy/' . ($transition ? 'tranzitie-' : 'analiza-') . now()->format('Ymd-His') . '.csv');
        @mkdir(dirname($csvPath), 0775, true);
        $csv = fopen($csvPath, 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['organizer_id', 'organizator', 'verdict', 'event_id', 'eveniment', 'final', 'ciorna', 'mutata_de_pe_org', 'net', 'platit',
            'in_procesare', 'suma_ciorna', 'se_aproba', 'ajustare', 'disponibil_acum', 'disponibil_dupa', 'sarite', 'probleme'], ',', '"', '\\');

        $results = [];
        $bar = $this->output->createProgressBar(count($ids));
        foreach ($ids as $id) {
            $org = MarketplaceOrganizer::find($id);
            if (! $org || ! LegacySettlementService::appliesTo((int) $org->marketplace_client_id)) {
                $bar->advance();

                continue;
            }
            $a = $service->analyze($org, $transition);
            $results[] = $a;
            foreach ($a['events'] as $e) {
                $ap = $e['approve'];
                fputcsv($csv, [$org->id, $org->name, $a['verdict'], $e['event_id'], $e['title'], $e['date'],
                    $ap['label'] ?? '', $ap && $ap['from_organizer_id'] !== $org->id ? $ap['from_organizer_id'] : '',
                    $e['net'], $e['paid'], $e['in_flight'], $ap['draft_amount'] ?? '', $ap['amount'] ?? '', $ap['adjustment'] ?? '',
                    $e['balance_before'], $e['balance_after'], implode(' | ', $e['skipped']), implode(' ', $e['issues'])], ',', '"', '\\');
            }
            $bar->advance();
        }
        $bar->finish();
        fclose($csv);
        $this->newLine(2);

        $by = collect($results)->groupBy('verdict');
        $this->table(['Verdict', 'Organizatori', 'Ciorne', 'Se aprobă (lei)'], collect(['ok', 'warn', 'blocked', 'settled', 'none'])->map(fn ($v) => [
            $v,
            ($by[$v] ?? collect())->count(),
            ($by[$v] ?? collect())->sum(fn ($a) => $a['totals']['payouts']),
            number_format(($by[$v] ?? collect())->sum(fn ($a) => $a['totals']['settle_amount']), 2, ',', '.'),
        ])->all());
        $this->info("Raport: {$csvPath}");

        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $verdicts = $this->option('include-warn') ? ['ok', 'warn'] : ['ok'];
        $toSettle = collect($results)->whereIn('verdict', $verdicts);
        if (! $this->confirm('Aprob ciornele pentru ' . $toSettle->count() . ' organizatori (' . implode(', ', $verdicts) . ')?', false)) {
            return self::FAILURE;
        }

        $done = 0;
        foreach ($toSettle as $a) {
            try {
                $service->settle(MarketplaceOrganizer::find($a['organizer_id']), null, true, $transition);
                $done++;
            } catch (\Throwable $e) {
                $this->warn("#{$a['organizer_id']} {$a['organizer']}: {$e->getMessage()}");
            }
        }
        $this->info("Aprobați: {$done}.");

        return self::SUCCESS;
    }

    private function finalize(LegacySettlementService $service): int
    {
        $rows = collect($service->finalize(false)['rows']);
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $cutoff = \Carbon\Carbon::parse(LegacySettlementService::CUTOFF_DATE)->endOfDay();
        $late = $rows->filter(fn ($r) => \Carbon\Carbon::createFromFormat('d.m.Y', $r['paid_at'])->gt($cutoff))->count();

        $this->table(['', ''], [
            ['Ciorne vechi aprobate de finalizat', $rows->count()],
            ['Suma', $fmt($rows->sum('amount'))],
            ['Organizatori', $rows->pluck('organizer_id')->unique()->count()],
            ['Data plății, cea mai veche', $rows->min(fn ($r) => \Carbon\Carbon::createFromFormat('d.m.Y', $r['paid_at']))?->format('d.m.Y') ?? '—'],
            ['Data plății, cea mai nouă', $rows->max(fn ($r) => \Carbon\Carbon::createFromFormat('d.m.Y', $r['paid_at']))?->format('d.m.Y') ?? '—'],
            ['Cu data plății după ' . $cutoff->format('d.m.Y') . ' (de verificat)', $late],
        ]);
        $this->table(['An plată', 'Deconturi', 'Sumă'], $rows->groupBy(fn ($r) => substr($r['paid_at'], -4))->sortKeys()
            ->map(fn ($g, $y) => [$y, $g->count(), $fmt($g->sum('amount'))])->values()->all());

        $path = (getenv('HOME') ?: storage_path('app')) . '/settle-legacy/finalizare-' . now()->format('Ymd-His') . '.csv';
        @mkdir(dirname($path), 0775, true);
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['decont_id', 'decont', 'organizer_id', 'organizator', 'event_id', 'eveniment', 'suma', 'data_platii', 'rezultat'], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, array_values($r), ',', '"', '\\');
        }
        fclose($fh);
        $this->info("Raport: {$path}");

        if (! $this->option('apply')) {
            $this->comment('Simulare — nu s-a scris nimic. Pentru scriere: --finalize --apply.');

            return self::SUCCESS;
        }
        if ($rows->isEmpty() || ! $this->confirm('Finalizez ' . $rows->count() . ' deconturi ca „plătit în sistemul anterior” (fără PDF, fără email)?', false)) {
            return self::SUCCESS;
        }

        $done = $service->finalize(true);
        $written = collect($done['rows'])->where('result', 'finalizat');
        $this->info('Finalizate: ' . $written->count() . ' (' . $fmt($written->sum('amount')) . " lei). Lot: {$done['batch']} (anulare: --revert-finalize={$done['batch']}).");
        foreach (collect($done['rows'])->filter(fn ($r) => str_starts_with($r['result'], 'sărit')) as $r) {
            $this->warn("Decont #{$r['payout_id']}: {$r['result']}");
        }

        return self::SUCCESS;
    }

    private function invitations(LegacySettlementService $service): int
    {
        $rows = collect($service->invitations(false)['rows']);
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $names = MarketplaceOrganizer::whereIn('id', $rows->pluck('organizer_id')->unique())->pluck('name', 'id');

        $this->table(['', ''], [
            ['Evenimente cu netul schimbat', $rows->count()],
            ['Organizatori', $rows->pluck('organizer_id')->unique()->count()],
            ['Net înainte', $fmt($rows->sum('net_old'))],
            ['Net corect', $fmt($rows->sum('net_new'))],
            ['Invitații scoase din net', $fmt($rows->sum('delta'))],
            ['a) „plătit în sistemul anterior” (ale noastre) — se reduc cu', $fmt($rows->sum('cut_a'))],
            ['b) aprobate de noi, neplătite — se reduc cu', $fmt($rows->sum('cut_b'))],
            ['Rămâne peste plăți reale / alte aprobate (nu se atinge)', $fmt($rows->sum('remaining'))],
            ['Evenimente cu rest peste plăți reale', $rows->where('remaining', '>', 0.004)->count()],
        ]);

        $this->table(['Org', 'Nume', 'Ev.', 'Invitații', 'Reducere a', 'Reducere b', 'Rest (real)'], $rows->groupBy('organizer_id')
            ->map(fn ($g, $o) => [$o, mb_substr((string) ($names[$o] ?? ''), 0, 28), $g->count(), $g->sum('delta'), $g->sum('cut_a'), $g->sum('cut_b'), $g->sum('remaining')])
            ->sortByDesc(3)->take(25)->map(fn ($r) => [$r[0], $r[1], $r[2], $fmt($r[3]), $fmt($r[4]), $fmt($r[5]), $fmt($r[6])])->values()->all());

        $path = (getenv('HOME') ?: storage_path('app')) . '/settle-legacy/invitatii-' . now()->format('Ymd-His') . '.csv';
        @mkdir(dirname($path), 0775, true);
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['event_id', 'eveniment', 'final', 'organizer_id', 'organizator', 'net_inainte', 'net_corect', 'invitatii',
            'a_platit_sistem_vechi', 'b_aprobate_noi', 'c_platite_real', 'd_alte_aprobate', 'reducere_a', 'reducere_b', 'rest_real', 'deconturi', 'rezultat'], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, [$r['event_id'], $r['event'], $r['end'], $r['organizer_id'], $names[$r['organizer_id']] ?? '', $r['net_old'], $r['net_new'], $r['delta'],
                $r['a'], $r['b'], $r['c'], $r['d'], $r['cut_a'], $r['cut_b'], $r['remaining'], $r['changes'], $r['result']], ',', '"', '\\');
        }
        fclose($fh);
        $this->info("Raport: {$path}");

        if (! $this->option('apply')) {
            $this->comment('Simulare — nu s-a scris nimic. Pentru scriere: --invitations --apply.');

            return self::SUCCESS;
        }
        if (! $this->confirm('Corectez deconturile a/b și pornesc calculul fără invitații?', false)) {
            return self::SUCCESS;
        }

        $done = $service->invitations(true);
        $written = collect($done['rows'])->where('result', 'corectat');
        $this->info('Evenimente corectate: ' . $written->count() . ". Lot: {$done['batch']} (anulare: --revert-invitations={$done['batch']}).");
        foreach (collect($done['rows'])->filter(fn ($r) => str_starts_with($r['result'], 'sărit')) as $r) {
            $this->warn("Eveniment #{$r['event_id']}: {$r['result']}");
        }

        return self::SUCCESS;
    }

    private function realign(LegacySettlementService $service): int
    {
        $apply = (bool) $this->option('apply');
        $all = (bool) $this->option('realign-all');

        // Întâi simularea; scrierea doar după confirmare, pe aceleași reguli.
        $result = $service->realign(false, $all);
        $rows = collect($result['rows']);
        $todo = $rows->where('result', 'de realiniat');

        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $this->table(['', ''], [
            ['Ciorne vechi aprobate verificate', $rows->count()],
            ['De realiniat', $todo->count()],
            ['Neschimbate (sold deja 0)', $rows->where('result', 'neschimbat')->count()],
            ['Suma lor acum', $fmt($todo->sum('old'))],
            ['Suma după realiniere', $fmt($todo->sum('new'))],
            ['Diferență („în procesare” scade cu)', $fmt($todo->sum('diff'))],
            ['Rămân la 0 (net sub ce e deja plătit)', $rows->filter(fn ($r) => str_contains($r['notes'], 'rămâne la 0'))->count()],
            ['Organizatori', $todo->pluck('organizer_id')->unique()->count()],
        ]);

        $path = (getenv('HOME') ?: storage_path('app')) . '/settle-legacy/realiniere-' . now()->format('Ymd-His') . '.csv';
        @mkdir(dirname($path), 0775, true);
        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['event_id', 'eveniment', 'organizer_id', 'decont_id', 'decont', 'net', 'platit', 'alte_in_procesare',
            'suma_acum', 'suma_noua', 'diferenta', 'observatii', 'rezultat'], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, [$r['event_id'], $r['event'], $r['organizer_id'], $r['payout_id'], $r['payout'], $r['net'], $r['paid'], $r['others'],
                $r['old'], $r['new'], $r['diff'], $r['notes'], $r['result']], ',', '"', '\\');
        }
        fclose($fh);
        $this->info("Raport: {$path}");

        if (! $apply) {
            $this->comment('Simulare — nu s-a scris nimic. Pentru scriere: --realign --apply.');

            return self::SUCCESS;
        }
        if ($todo->isEmpty() || ! $this->confirm('Realiniez ' . $todo->count() . ' ciorne (fără PDF, fără email)?', false)) {
            return self::SUCCESS;
        }

        $done = $service->realign(true, $all);
        $written = collect($done['rows'])->where('result', 'realiniat')->count();
        $this->info("Realiniate: {$written}. Lot: {$done['batch']} (anulare: --revert-realign={$done['batch']}).");
        foreach (collect($done['rows'])->filter(fn ($r) => str_starts_with($r['result'], 'sărit')) as $r) {
            $this->warn("Decont #{$r['payout_id']}: {$r['result']}");
        }

        return self::SUCCESS;
    }
}

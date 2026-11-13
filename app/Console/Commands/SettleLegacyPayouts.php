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
        {--revert-realign= : Anulează un lot de realiniere}';

    protected $description = 'Ciornele vechi AmBilet: analizează / aprobă ciornele „În așteptare” pe evenimentele încheiate până la ' . LegacySettlementService::CUTOFF_DATE . '.';

    public function handle(LegacySettlementService $service): int
    {
        if ($id = $this->option('revert-organizer')) {
            $org = MarketplaceOrganizer::findOrFail((int) $id);
            if (! $this->confirm("Anulez ultima aprobare pentru {$org->name}?", false)) {
                return self::FAILURE;
            }
            $this->info('Readuse: ' . $service->revert($org, null) . ' ciorne.');

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

        $ids = $this->option('organizer')
            ? [(int) $this->option('organizer')]
            : LegacySettlementService::candidateOrganizerIds(LegacySettlementService::MARKETPLACE_ID);

        $csvPath = $this->option('csv') ?: ((getenv('HOME') ?: storage_path('app')) . '/settle-legacy/analiza-' . now()->format('Ymd-His') . '.csv');
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
            $a = $service->analyze($org);
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
                $service->settle(MarketplaceOrganizer::find($a['organizer_id']), null, true);
                $done++;
            } catch (\Throwable $e) {
                $this->warn("#{$a['organizer_id']} {$a['organizer']}: {$e->getMessage()}");
            }
        }
        $this->info("Aprobați: {$done}.");

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

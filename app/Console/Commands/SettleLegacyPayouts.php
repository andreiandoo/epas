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
        {--apply : Rezolvă organizatorii cu verdict OK}
        {--include-warn : Cu --apply, rezolvă și organizatorii cu verdict Atenție}
        {--revert-organizer= : Anulează ultima rezolvare a acestui organizator}';

    protected $description = 'Soldurile din trecut AmBilet: analizează / marchează plătite deconturile vechi pe evenimentele încheiate până la ' . LegacySettlementService::CUTOFF_DATE . '.';

    public function handle(LegacySettlementService $service): int
    {
        if ($id = $this->option('revert-organizer')) {
            $org = MarketplaceOrganizer::findOrFail((int) $id);
            if (! $this->confirm("Anulez ultima rezolvare pentru {$org->name}?", false)) {
                return self::FAILURE;
            }
            $this->info('Readuse: ' . $service->revert($org, null) . ' deconturi.');

            return self::SUCCESS;
        }

        $ids = $this->option('organizer')
            ? [(int) $this->option('organizer')]
            : LegacySettlementService::candidateOrganizerIds(LegacySettlementService::MARKETPLACE_ID);

        $csvPath = $this->option('csv') ?: ((getenv('HOME') ?: storage_path('app')) . '/settle-legacy/analiza-' . now()->format('Ymd-His') . '.csv');
        @mkdir(dirname($csvPath), 0775, true);
        $csv = fopen($csvPath, 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['organizer_id', 'organizator', 'verdict', 'event_id', 'eveniment', 'final', 'deconturi', 'net', 'platit_deja',
            'ciorna', 'se_deconteaza', 'ajustare', 'sold_acum', 'sold_dupa', 'probleme']);

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
                fputcsv($csv, [$org->id, $org->name, $a['verdict'], $e['event_id'], $e['title'], $e['date'],
                    collect($e['payouts'])->map(fn ($p) => $p['label'] . ' ' . $p['status'] . ($p['organizer_id'] !== $org->id ? ' org#' . $p['organizer_id'] : ''))->implode(' | '),
                    $e['net'], $e['paid'], $e['draft_amount'], $e['settle_amount'], $e['adjustment'], $e['balance_before'], $e['balance_after'],
                    implode(' ', $e['issues'])]);
            }
            $bar->advance();
        }
        $bar->finish();
        fclose($csv);
        $this->newLine(2);

        $by = collect($results)->groupBy('verdict');
        $this->table(['Verdict', 'Organizatori', 'Deconturi', 'Se decontează (lei)'], collect(['ok', 'warn', 'blocked', 'settled', 'none'])->map(fn ($v) => [
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
        if (! $this->confirm('Rezolv ' . $toSettle->count() . ' organizatori (' . implode(', ', $verdicts) . ')?', false)) {
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
        $this->info("Rezolvați: {$done}.");

        return self::SUCCESS;
    }
}

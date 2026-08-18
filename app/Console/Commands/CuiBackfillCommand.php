<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for ambilet organizers whose company_tax_id and
 * company_registration got imported incorrectly. Reads the manually
 * curated CSV `cui-cu-probleme.csv` at the app root and previews /
 * applies updates.
 *
 * Removed together with the CSV after prod runs.
 */
class CuiBackfillCommand extends Command
{
    protected $signature = 'cui:backfill
        {--apply : Persist changes (default is dry-run)}
        {--file=cui-cu-probleme.csv : CSV path relative to app root}';

    protected $description = 'Backfill CUI + Reg Com on ambilet organizers from CSV (dry-run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $file = base_path($this->option('file'));

        if (!is_readable($file)) {
            $this->error("Nu găsesc CSV la: {$file}");
            return self::FAILURE;
        }

        $fh = fopen($file, 'r');
        $header = fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) < 4) continue;
            $rows[] = $r;
        }
        fclose($fh);

        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . ' Procesez ' . count($rows) . ' rânduri din CSV...');
        $this->newLine();

        $changes = 0;
        $unchanged = 0;
        $issues = 0;
        $updateSet = [];

        foreach ($rows as $r) {
            $id = (int) $r[0];
            $csvName = trim((string) $r[1]);
            $csvCui = trim((string) $r[2]);
            $csvReg = trim((string) $r[3]);

            $o = MarketplaceOrganizer::find($id);
            if (!$o) {
                $this->line("<fg=red>[MISSING]</> id={$id} — {$csvName}");
                $issues++;
                continue;
            }
            if ((int) $o->marketplace_client_id !== 1) {
                $this->line("<fg=red>[WRONG-MP]</> id={$id} mc_id={$o->marketplace_client_id} — {$csvName}");
                $issues++;
                continue;
            }

            $dbCui = trim((string) $o->company_tax_id);
            $dbReg = trim((string) $o->company_registration);
            $cuiChange = $dbCui !== $csvCui;
            $regChange = $dbReg !== $csvReg;

            if (!$cuiChange && !$regChange) {
                $unchanged++;
                continue;
            }
            $changes++;

            $parts = [];
            if ($cuiChange) {
                $parts[] = 'CUI: ' . ($dbCui === '' ? '(gol)' : "\"{$dbCui}\"") . " → \"{$csvCui}\"";
            }
            if ($regChange) {
                $parts[] = 'REG: ' . ($dbReg === '' ? '(gol)' : "\"{$dbReg}\"") . ' → ' . ($csvReg === '' ? '(gol)' : "\"{$csvReg}\"");
            }
            $this->line(str_pad((string) $id, 4) . ' | ' . str_pad(mb_substr($csvName, 0, 42), 42) . ' | ' . implode(' | ', $parts));

            $updateSet[] = [
                'id' => $id,
                'cui' => $csvCui,
                'reg' => $csvReg,
            ];
        }

        $this->newLine();
        $this->info('SUMMARY:');
        $this->line("  Total CSV: " . count($rows));
        $this->line("  Would change: {$changes}");
        $this->line("  Unchanged (already correct): {$unchanged}");
        $this->line("  Issues (missing / wrong marketplace): {$issues}");
        $this->newLine();

        if ($issues > 0) {
            $this->error('Sunt issues — nu aplic nimic până nu clarifici.');
            return self::FAILURE;
        }

        if (!$apply) {
            $this->comment('[DRY-RUN] Nu s-a scris nimic. Rulează cu --apply pentru a persista.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updateSet) {
            foreach ($updateSet as $u) {
                MarketplaceOrganizer::where('id', $u['id'])->update([
                    'company_tax_id' => $u['cui'],
                    'company_registration' => $u['reg'],
                ]);
            }
        });

        $this->info('✓ Actualizate ' . count($updateSet) . ' organizatori.');
        return self::SUCCESS;
    }
}

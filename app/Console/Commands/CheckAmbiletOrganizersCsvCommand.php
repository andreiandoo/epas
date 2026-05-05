<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;

/**
 * Cross-check `Firma` (company_name) and `CUI` (company_tax_id) for every
 * organizer in a marketplace against an authoritative CSV. Read-only. Prints
 * a table of mismatches and optionally writes a CSV report.
 *
 * Match strategy per CSV row:
 *   1. USER ID → marketplace_organizers.id (the model IS the user — it
 *      extends Authenticatable, so its id is the login identity)
 *   2. Normalized Firma → company_name (last-resort fallback)
 *
 * CUI normalisation handles real-world variants like:
 *   - "37913745/2017" (with /year suffix)
 *   - "RO47073874"   (with RO prefix)
 *   - "47438350"     (plain digits)
 * — all collapse to "47438350"-style digits-only for comparison.
 */
class CheckAmbiletOrganizersCsvCommand extends Command
{
    protected $signature = 'check:ambilet-organizers-csv
        {file=resources/marketplaces/ambilet/check/check_organizatori.csv : CSV path (relative to base_path or absolute)}
        {--marketplace=1 : marketplace_client_id to scope the comparison}
        {--out= : Write a CSV report to this path}
        {--show-ok : Include matching rows in the output (default: only mismatches)}
        {--missing-in-csv : Also list DB organizers absent from the CSV}';

    protected $description = 'Verify Ambilet organizer Firma + CUI against the production DB';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!str_starts_with($path, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error("CSV not found: {$path}");
            return self::FAILURE;
        }

        $marketplaceId = (int) $this->option('marketplace');

        $organizers = MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplaceId)
            ->get(['id', 'name', 'company_name', 'company_tax_id']);

        $byId      = $organizers->keyBy('id');
        $byNameKey = $organizers->keyBy(fn ($o) => $this->normName($o->company_name ?? $o->name));

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (!$header) {
            $this->error('Empty CSV.');
            fclose($fh);
            return self::FAILURE;
        }
        // Strip BOM from first column header if present.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $idx = array_flip($header);

        foreach (['Firma', 'CUI', 'USER ID'] as $col) {
            if (!isset($idx[$col])) {
                $this->error("Missing CSV column: {$col} (have: " . implode(', ', $header) . ')');
                fclose($fh);
                return self::FAILURE;
            }
        }

        $report = [['line', 'csv_user_id', 'csv_firma', 'csv_cui', 'matched_by', 'db_id', 'db_company_name', 'db_company_tax_id', 'name_status', 'cui_status', 'overall']];
        $totals = ['rows' => 0, 'matched' => 0, 'unmatched' => 0, 'name_mismatch' => 0, 'cui_mismatch' => 0, 'ok' => 0];
        $matchedDbIds = [];

        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $totals['rows']++;

            $csvUserId = trim($row[$idx['USER ID']] ?? '');
            $csvFirma  = trim($row[$idx['Firma']] ?? '');
            $csvCui    = trim($row[$idx['CUI']] ?? '');

            $matched   = null;
            $matchedBy = '-';
            if ($csvUserId !== '' && ctype_digit($csvUserId)) {
                $uid = (int) $csvUserId;
                if (isset($byId[$uid])) {
                    $matched   = $byId[$uid];
                    $matchedBy = 'organizer.id';
                }
            }
            if (!$matched && $csvFirma !== '') {
                $key = $this->normName($csvFirma);
                if (isset($byNameKey[$key])) {
                    $matched   = $byNameKey[$key];
                    $matchedBy = 'company_name';
                }
            }

            if (!$matched) {
                $totals['unmatched']++;
                $report[] = [$line, $csvUserId, $csvFirma, $csvCui, '-', '-', '-', '-', '-', '-', 'NOT_FOUND'];
                continue;
            }

            $totals['matched']++;
            $matchedDbIds[$matched->id] = true;

            $dbName = (string) ($matched->company_name ?? '');
            $dbCui  = (string) ($matched->company_tax_id ?? '');

            $nameOk = $this->normName($csvFirma) === $this->normName($dbName);
            $cuiOk  = $this->normCui($csvCui) === $this->normCui($dbCui);

            if (!$nameOk) $totals['name_mismatch']++;
            if (!$cuiOk)  $totals['cui_mismatch']++;
            if ($nameOk && $cuiOk) $totals['ok']++;

            if ($this->option('show-ok') || !$nameOk || !$cuiOk) {
                $report[] = [
                    $line,
                    $csvUserId,
                    $csvFirma,
                    $csvCui,
                    $matchedBy,
                    $matched->id,
                    $dbName,
                    $dbCui,
                    $nameOk ? 'OK' : 'MISMATCH',
                    $cuiOk  ? 'OK' : 'MISMATCH',
                    ($nameOk && $cuiOk) ? 'OK' : 'MISMATCH',
                ];
            }
        }
        fclose($fh);

        if ($this->option('missing-in-csv')) {
            foreach ($organizers as $o) {
                if (!isset($matchedDbIds[$o->id])) {
                    $report[] = [
                        '-', '-', '-', '-',
                        'db-only',
                        $o->id,
                        (string) ($o->company_name ?? ''),
                        (string) ($o->company_tax_id ?? ''),
                        'N/A', 'N/A', 'NOT_IN_CSV',
                    ];
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Marketplace %d | CSV rows: %d | matched: %d | unmatched (in CSV, no DB row): %d | name mismatches: %d | CUI mismatches: %d | OK: %d',
            $marketplaceId,
            $totals['rows'], $totals['matched'], $totals['unmatched'],
            $totals['name_mismatch'], $totals['cui_mismatch'], $totals['ok']
        ));

        $out = $this->option('out');
        if ($out) {
            if (!str_starts_with($out, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $out)) {
                $out = base_path($out);
            }
            $dir = dirname($out);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fhOut = fopen($out, 'w');
            foreach ($report as $r) {
                fputcsv($fhOut, $r);
            }
            fclose($fhOut);
            $this->info("Report written to: {$out}");
        } else {
            $this->table($report[0], array_slice($report, 1));
        }

        return self::SUCCESS;
    }

    private function normName(?string $v): string
    {
        $v = (string) $v;
        $v = mb_strtolower(trim($v));
        $v = strtr($v, [
            'ş' => 's', 'ţ' => 't', 'ș' => 's', 'ț' => 't',
            'ă' => 'a', 'â' => 'a', 'î' => 'i',
        ]);
        $v = str_replace(['.', ',', '"', "'", '-'], ['', '', '', '', ' '], $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return trim($v);
    }

    private function normCui(?string $v): string
    {
        $v = (string) $v;
        $v = preg_replace('/^\s*RO\s*/i', '', $v);
        if (preg_match('/^(\d+)/', $v, $m)) {
            return $m[1];
        }
        return preg_replace('/\D+/', '', $v);
    }
}

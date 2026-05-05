<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Backfill `contract_number_series` and `contract_date` on Ambilet
 * marketplace organizers from the authoritative CSV. Default is dry-run;
 * pass --apply to actually write.
 *
 * Strict 100% match per row is required:
 *   - row matches an organizer (by USER ID → id, fallback by name)
 *   - normalised CSV Firma == DB company_name
 *   - normalised CSV CUI   == DB company_tax_id
 * If any of those fails, the row is skipped (and reported).
 *
 * The CSV column "CTR.nr. (14)" looks like "AMB179/14.05.22". Variations
 * handled:
 *   - spaces around the slash:        "AMB486 / 21.08.2025"
 *   - 2-digit and 4-digit years:      "14.05.22" / "01.11.2024"
 *   - trailing free text:             "AMB82/10.03.20 nesemnat"
 *     → "nesemnat" means unsigned, the whole row is skipped
 *   - placeholders / invalid:         "AMB"  → skipped
 *
 * Default write rule (safe): only fills BOTH fields when both are
 * currently empty in DB. With --overwrite, always replaces with CSV
 * values.
 */
class UpdateAmbiletOrganizerContractsCommand extends Command
{
    protected $signature = 'update:ambilet-organizer-contracts
        {file=resources/marketplaces/ambilet/check/check_organizatori.csv : CSV path (relative to base_path or absolute)}
        {--marketplace=1 : marketplace_client_id to scope the comparison}
        {--apply : Actually write changes to the DB (default is dry-run)}
        {--overwrite : Replace existing contract_number_series / contract_date even when set}
        {--out= : Write a CSV report to this path}';

    protected $description = 'Backfill contract number/date for Ambilet organizers from the CSV (strict 100% match)';

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
        $apply         = (bool) $this->option('apply');
        $overwrite     = (bool) $this->option('overwrite');

        $organizers = MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplaceId)
            ->get(['id', 'name', 'company_name', 'company_tax_id', 'contract_number_series', 'contract_date']);

        $byId      = $organizers->keyBy('id');
        $byNameKey = $organizers->keyBy(fn ($o) => $this->normName($o->company_name ?? $o->name));

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (!$header) {
            $this->error('Empty CSV.');
            fclose($fh);
            return self::FAILURE;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $idx = array_flip($header);

        foreach (['Firma', 'CUI', 'USER ID', 'CTR.nr. (14)'] as $col) {
            if (!isset($idx[$col])) {
                $this->error("Missing CSV column: {$col} (have: " . implode(', ', $header) . ')');
                fclose($fh);
                return self::FAILURE;
            }
        }

        $report = [['line', 'csv_user_id', 'csv_firma', 'csv_cui', 'csv_ctr', 'db_id', 'db_company_name', 'db_company_tax_id', 'db_series_before', 'db_date_before', 'parsed_series', 'parsed_date', 'status']];
        $totals = [
            'rows' => 0, 'updated' => 0, 'already_ok' => 0, 'would_change_existing' => 0,
            'skip_name_mismatch' => 0, 'skip_cui_mismatch' => 0, 'skip_not_found' => 0,
            'skip_invalid_ctr' => 0, 'skip_unsigned' => 0,
        ];

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
            $csvCtr    = trim($row[$idx['CTR.nr. (14)']] ?? '');

            // 1. Resolve organizer
            $matched = null;
            if ($csvUserId !== '' && ctype_digit($csvUserId) && isset($byId[(int)$csvUserId])) {
                $matched = $byId[(int)$csvUserId];
            } elseif ($csvFirma !== '') {
                $key = $this->normName($csvFirma);
                if (isset($byNameKey[$key])) {
                    $matched = $byNameKey[$key];
                }
            }

            $base = [$line, $csvUserId, $csvFirma, $csvCui, $csvCtr];

            if (!$matched) {
                $totals['skip_not_found']++;
                $report[] = array_merge($base, ['-', '-', '-', '-', '-', '-', '-', 'SKIP_NOT_FOUND']);
                continue;
            }

            $dbName = (string) ($matched->company_name ?? '');
            $dbCui  = (string) ($matched->company_tax_id ?? '');

            // 2. Strict 100% match: name AND cui
            $nameOk = $this->normName($csvFirma) === $this->normName($dbName);
            $cuiOk  = $this->normCui($csvCui)  === $this->normCui($dbCui);

            $dbSeriesBefore = (string) ($matched->contract_number_series ?? '');
            $dbDateBefore   = $matched->contract_date ? $matched->contract_date->format('Y-m-d') : '';

            if (!$nameOk) {
                $totals['skip_name_mismatch']++;
                $report[] = array_merge($base, [$matched->id, $dbName, $dbCui, $dbSeriesBefore, $dbDateBefore, '-', '-', 'SKIP_NAME_MISMATCH']);
                continue;
            }
            if (!$cuiOk) {
                $totals['skip_cui_mismatch']++;
                $report[] = array_merge($base, [$matched->id, $dbName, $dbCui, $dbSeriesBefore, $dbDateBefore, '-', '-', 'SKIP_CUI_MISMATCH']);
                continue;
            }

            // 3. Parse CTR.nr.
            if ($csvCtr === '') {
                $totals['skip_invalid_ctr']++;
                $report[] = array_merge($base, [$matched->id, $dbName, $dbCui, $dbSeriesBefore, $dbDateBefore, '-', '-', 'SKIP_INVALID_CTR']);
                continue;
            }
            if (stripos($csvCtr, 'nesemnat') !== false) {
                $totals['skip_unsigned']++;
                $report[] = array_merge($base, [$matched->id, $dbName, $dbCui, $dbSeriesBefore, $dbDateBefore, '-', '-', 'SKIP_UNSIGNED']);
                continue;
            }
            $parsed = $this->parseCtr($csvCtr);
            if (!$parsed) {
                $totals['skip_invalid_ctr']++;
                $report[] = array_merge($base, [$matched->id, $dbName, $dbCui, $dbSeriesBefore, $dbDateBefore, '-', '-', 'SKIP_INVALID_CTR']);
                continue;
            }
            $newSeries = $parsed['series'];
            $newDate   = $parsed['date']; // Carbon

            // 4. Decide what to write
            $hasSeries = $dbSeriesBefore !== '';
            $hasDate   = $dbDateBefore !== '';
            $seriesMatches = $hasSeries && strcasecmp($dbSeriesBefore, $newSeries) === 0;
            $dateMatches   = $hasDate   && $dbDateBefore === $newDate->format('Y-m-d');

            $status = null;
            $write = false;
            if ($hasSeries && $hasDate && $seriesMatches && $dateMatches) {
                $status = 'ALREADY_OK';
                $totals['already_ok']++;
            } elseif (!$hasSeries && !$hasDate) {
                $status = 'UPDATED';
                $write = true;
            } else {
                // Some value already present and diverges (or partial fill).
                if ($overwrite) {
                    $status = 'UPDATED';
                    $write = true;
                } else {
                    $status = 'WOULD_CHANGE_EXISTING';
                    $totals['would_change_existing']++;
                }
            }

            if ($write) {
                $totals['updated']++;
                if ($apply) {
                    $matched->forceFill([
                        'contract_number_series' => $newSeries,
                        'contract_date'          => $newDate->toDateString(),
                    ])->save();
                }
            }

            $report[] = array_merge($base, [
                $matched->id,
                $dbName,
                $dbCui,
                $dbSeriesBefore,
                $dbDateBefore,
                $newSeries,
                $newDate->format('Y-m-d'),
                $status . ($write && !$apply ? ' (dry-run)' : ''),
            ]);
        }
        fclose($fh);

        $this->newLine();
        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $ow   = $overwrite ? ' overwrite=on' : '';
        $this->info(sprintf(
            '[%s%s] Marketplace %d | rows: %d | UPDATED: %d | ALREADY_OK: %d | WOULD_CHANGE_EXISTING: %d',
            $mode, $ow, $marketplaceId, $totals['rows'],
            $totals['updated'], $totals['already_ok'], $totals['would_change_existing']
        ));
        $this->info(sprintf(
            'Skipped — name mismatch: %d | CUI mismatch: %d | not found: %d | invalid CTR: %d | unsigned: %d',
            $totals['skip_name_mismatch'], $totals['skip_cui_mismatch'], $totals['skip_not_found'],
            $totals['skip_invalid_ctr'], $totals['skip_unsigned']
        ));
        if (!$apply && $totals['updated'] > 0) {
            $this->warn('Dry-run: nothing was written. Re-run with --apply to commit changes.');
        }

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
        }

        return self::SUCCESS;
    }

    private function parseCtr(string $raw): ?array
    {
        $raw = trim($raw);
        // Tolerate spaces around the slash and trailing free-text after the date.
        $parts = preg_split('#\s*/\s*#', $raw, 2);
        if (count($parts) !== 2) return null;
        $series = trim($parts[0]);
        $datePart = trim($parts[1]);

        // Capture only the leading dd.mm.(yy|yyyy) — defensive against trailing text.
        if (!preg_match('/^(\d{1,2}\.\d{1,2}\.\d{2,4})/', $datePart, $m)) {
            return null;
        }
        $datePart = $m[1];

        // Accept AMB + any reasonable suffix: digits, dots, dashes, parens,
        // trailing letters denoting addendum/version (AMB34.2, AMB03-1,
        // AMB317AB4, AMB481(56), AMB58.). The literal "AMB" alone stays
        // rejected because the + quantifier requires ≥1 char after AMB.
        if (!preg_match('/^AMB[A-Za-z0-9._\-()]+$/', $series)) {
            return null;
        }
        $date = $this->parseDate($datePart);
        if (!$date) {
            return null;
        }
        return ['series' => $series, 'date' => $date];
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        // Try 4-digit year first (more specific).
        foreach (['d.m.Y', 'd.m.y'] as $fmt) {
            $d = Carbon::createFromFormat('!' . $fmt, $raw);
            // Round-trip check guards against Carbon "lenient" parsing.
            if ($d && $d->format($fmt) === $raw) {
                return $d;
            }
        }
        return null;
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

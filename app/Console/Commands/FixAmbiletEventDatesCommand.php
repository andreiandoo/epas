<?php

namespace App\Console\Commands;

use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletEventDatesCommand extends Command
{
    protected $signature = 'fix:ambilet-event-dates
        {csv : Path to event_dates_fix.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--skip-dates : Do not update event_date / start_time / end_time / range fields}
        {--skip-archive : Do not archive events past their expirare_eveniment}';

    protected $description = 'Fix imported AmBilet event dates: start/end times, range mode, and archive past events';

    public function handle(): int
    {
        $csvFile  = $this->argument('csv');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $now      = new DateTime();

        if (!file_exists($csvFile)) {
            $this->error("CSV not found: {$csvFile}");
            return 1;
        }

        $mapFile = storage_path('app/import_maps/events_map.json');
        if (!file_exists($mapFile)) {
            $this->error('events_map.json not found.');
            return 1;
        }
        $eventsMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded events map: ' . count($eventsMap) . ' entries.');

        $fh     = fopen($csvFile, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\');

        $updated = $skipped = $notFound = $archived = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data      = array_combine($header, $row);
            $wpEventId = $data['wp_event_id'];
            $tixelloId = $eventsMap[$wpEventId] ?? null;

            if (!$tixelloId) {
                $notFound++;
                continue;
            }

            $fields = [];

            // ----------------------------------------------------------------
            // DATE / TIME FIELDS
            // ----------------------------------------------------------------
            if (!$this->option('skip-dates')) {
                $startDt  = $this->parseDateTime($data['event_date_time']     ?? null);
                $endDt    = $this->parseDateTime($data['event_end_date_time']  ?? null);
                $rangeSt  = $this->parseYmd($data['data_eveniment_start']     ?? null);
                $rangeEnd = $this->parseYmd($data['data_eveniment_end']        ?? null);

                if ($startDt) {
                    $startDate = $startDt->format('Y-m-d');
                    $startTime = $startDt->format('H:i');

                    if ($rangeSt && $rangeEnd && $rangeSt !== $rangeEnd) {
                        // Explicit multi-day range from data_eveniment_start / data_eveniment_end
                        $fields['duration_mode']    = 'range';
                        $fields['range_start_date'] = $rangeSt;
                        $fields['range_end_date']   = $rangeEnd;
                        $fields['range_start_time'] = $startTime;
                        $fields['range_end_time']   = $endDt ? $endDt->format('H:i') : null;
                        // Clear single-day fields
                        $fields['event_date'] = null;
                        $fields['start_time'] = null;
                        $fields['end_time']   = null;

                    } elseif ($endDt && $endDt->format('Y-m-d') !== $startDate) {
                        // Start and end on different days → range
                        $fields['duration_mode']    = 'range';
                        $fields['range_start_date'] = $startDate;
                        $fields['range_end_date']   = $endDt->format('Y-m-d');
                        $fields['range_start_time'] = $startTime;
                        $fields['range_end_time']   = $endDt->format('H:i');
                        $fields['event_date'] = null;
                        $fields['start_time'] = null;
                        $fields['end_time']   = null;

                    } else {
                        // Single day
                        $fields['duration_mode'] = 'single_day';
                        $fields['event_date']    = $startDate;
                        $fields['start_time']    = $startTime;
                        $fields['end_time']      = $endDt ? $endDt->format('H:i') : null;
                        // Clear range fields
                        $fields['range_start_date'] = null;
                        $fields['range_end_date']   = null;
                        $fields['range_start_time'] = null;
                        $fields['range_end_time']   = null;
                    }
                }
            }

            // ----------------------------------------------------------------
            // ARCHIVE — set status=archived if expirare_eveniment is in the past
            // ----------------------------------------------------------------
            if (!$this->option('skip-archive')) {
                $expiry = $this->parseDateTime($data['expirare_eveniment'] ?? null);
                if ($expiry && $expiry < $now) {
                    $fields['status']       = 'archived';
                    $fields['is_published'] = 0;
                    $archived++;
                }
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if ($dryRun) {
                $keys = implode(', ', array_keys($fields));
                $this->line("[DRY RUN] Event #{$tixelloId} (wp:{$wpEventId}) → {$keys}");
                $updated++;
                continue;
            }

            DB::table('events')
                ->where('id', $tixelloId)
                ->where('marketplace_client_id', $clientId)
                ->update($fields);

            $updated++;

            if ($updated % 100 === 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        fclose($fh);

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix}: {$updated} events (of which ~{$archived} archived) | Skipped: {$skipped} | Not in map: {$notFound}");

        return 0;
    }

    /**
     * Parse "2026-08-29 20:00" or "2026-08-23 23:00:00" into DateTime.
     */
    private function parseDateTime(?string $v): ?DateTime
    {
        $v = $this->n($v);
        if (!$v) {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $v)
            ?: DateTime::createFromFormat('Y-m-d H:i', $v)
            ?: null;
        return $dt;
    }

    /**
     * Parse "20260821" (YYYYMMDD) into "2026-08-21".
     */
    private function parseYmd(?string $v): ?string
    {
        $v = $this->n($v);
        if (!$v || strlen($v) < 8) {
            return null;
        }
        $dt = DateTime::createFromFormat('Ymd', substr($v, 0, 8));
        return $dt ? $dt->format('Y-m-d') : null;
    }

    private function n(?string $v): ?string
    {
        return ($v !== null && $v !== '' && $v !== 'NULL') ? $v : null;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixAmbiletTicketTypeSeriesCommand extends Command
{
    protected $signature = 'fix:ambilet-ticket-type-series
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Generate missing SKU and series_start/series_end for imported ticket types';

    public function handle(): int
    {
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        $eventIds = DB::table('events')
            ->where('marketplace_client_id', $clientId)
            ->pluck('id');

        // Load event_series map: event_id → event_series
        $eventSeriesMap = DB::table('events')
            ->whereIn('id', $eventIds)
            ->pluck('event_series', 'id')
            ->all();

        $ticketTypes = DB::table('ticket_types')
            ->whereIn('event_id', $eventIds)
            ->select('id', 'event_id', 'name', 'sku', 'series_start', 'series_end', 'quota_total')
            ->get();

        $this->info("Found {$ticketTypes->count()} ticket types for marketplace {$clientId}.");

        $skuFixed = $seriesFixed = $skipped = 0;

        // Track SKUs per event to avoid duplicates
        $skusByEvent = [];

        foreach ($ticketTypes as $tt) {
            $fields = [];

            // Generate SKU if missing
            if (empty($tt->sku)) {
                $baseSku = Str::limit(Str::upper(Str::slug($tt->name ?: 'TICKET', '-')), 58, '');
                // Ensure unique within event
                $eventSkus = $skusByEvent[$tt->event_id] ?? [];
                $sku = $baseSku;
                $suffix = 2;
                while (in_array($sku, $eventSkus)) {
                    $sku = $baseSku . '-' . $suffix;
                    $suffix++;
                }
                $skusByEvent[$tt->event_id][] = $sku;
                $fields['sku'] = $sku;
                $skuFixed++;
            } else {
                $skusByEvent[$tt->event_id][] = $tt->sku;
            }

            // Generate series if missing
            if (empty($tt->series_start)) {
                $eventSeries = $eventSeriesMap[$tt->event_id] ?? ('EVT-' . $tt->event_id);
                $ttIdentifier = $tt->id;
                $capacity = (int) ($tt->quota_total ?? 0);
                if ($capacity <= 0) $capacity = 1000; // -1 (unlimited) or 0 → default 1000

                $fields['series_start'] = $eventSeries . '-' . $ttIdentifier . '-' . str_pad(1, 5, '0', STR_PAD_LEFT);
                $fields['series_end'] = $eventSeries . '-' . $ttIdentifier . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT);
                $seriesFixed++;
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if (! $dryRun) {
                DB::table('ticket_types')->where('id', $tt->id)->update($fields);
            }

            if (($skuFixed + $seriesFixed) % 500 === 0) {
                $this->line("Progress: {$skuFixed} SKUs, {$seriesFixed} series...");
            }
        }

        $prefix = $dryRun ? '[DRY RUN] Would fix' : 'Fixed';
        $this->info("{$prefix}: {$skuFixed} SKUs | {$seriesFixed} series | Skipped (already set): {$skipped}");

        return 0;
    }
}

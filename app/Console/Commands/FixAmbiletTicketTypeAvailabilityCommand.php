<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletTicketTypeAvailabilityCommand extends Command
{
    protected $signature = 'fix:ambilet-ticket-type-availability
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Fix ticket type availability: deactivate removed products, set scheduling, sale prices, and sold-out status';

    public function handle(): int
    {
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $basePath = resource_path('marketplaces/ambilet/old_database/product_stock');

        // Load maps
        $eventsMapFile = storage_path('app/import_maps/events_map.json');
        $ttMapFile     = storage_path('app/import_maps/ticket_types_map.json');
        if (! file_exists($eventsMapFile) || ! file_exists($ttMapFile)) {
            $this->error('Map files not found.');
            return 1;
        }
        $eventsMap = json_decode(file_get_contents($eventsMapFile), true);
        $ttMap     = json_decode(file_get_contents($ttMapFile), true);

        // Reverse TT map: tixello_tt_id → wp_product_id
        $reverseTtMap = array_flip($ttMap);

        // 1. Parse event_active_products.csv → wp_event_id → [wp_product_ids]
        $this->info('Loading active products per event...');
        $activeProductsPerEvent = $this->loadActiveProducts($basePath . '/event_active_products.csv');
        $this->info('  Events with bilete_eveniment: ' . count($activeProductsPerEvent));

        // 2. Parse product_availability.csv → wp_product_id → availability data
        $this->info('Loading product availability...');
        $productAvailability = $this->loadProductAvailability($basePath . '/product_availability.csv');
        $this->info('  Products with availability data: ' . count($productAvailability));

        // 3. Load all ticket types for this marketplace
        $eventIds = DB::table('events')->where('marketplace_client_id', $clientId)->pluck('id');
        $ticketTypes = DB::table('ticket_types')
            ->whereIn('event_id', $eventIds)
            ->select('id', 'event_id', 'status', 'meta')
            ->get();

        $this->info("Ticket types to check: {$ticketTypes->count()}");

        // Build event_id → wp_event_id reverse map
        $reverseEventsMap = array_flip($eventsMap);

        $deactivated = $saleSet = $soldOut = $skipped = 0;

        foreach ($ticketTypes as $tt) {
            $wpProductId = $reverseTtMap[$tt->id] ?? null;
            if (! $wpProductId) {
                $skipped++;
                continue;
            }

            $wpEventId = $reverseEventsMap[$tt->event_id] ?? null;
            $fields = [];

            // Check 1: Is this product still in the event's bilete_eveniment?
            if ($wpEventId && isset($activeProductsPerEvent[$wpEventId])) {
                $activeProducts = $activeProductsPerEvent[$wpEventId];
                if (! in_array($wpProductId, $activeProducts)) {
                    // Product was removed from event
                    $fields['status'] = 'hidden';
                    $deactivated++;
                }
            }

            // Check 2: Product availability data
            $avail = $productAvailability[$wpProductId] ?? null;
            if ($avail) {
                // Sold out — only count, don't change quota_total
                // Most ambilet products are outofstock because they're historical (all sold)
                // quota_total is already -1 (unlimited) which is correct for sold historical events
                if ($avail['stock_status'] === 'outofstock' && ! isset($fields['status'])) {
                    $soldOut++;
                }

                // NOTE: _ticket_availability_from_date and _ticket_availability_to_date
                // control the VALIDITY/CHECK-IN window, NOT the sale availability.
                // A ticket can be on sale NOW but only valid for scanning on a future date.
                // Therefore we do NOT use these dates for scheduled_at or active_until.

                // Sale price + dates
                if (! empty($avail['sale_price']) && (float) $avail['sale_price'] > 0) {
                    $fields['sale_price_cents'] = (int) round((float) $avail['sale_price'] * 100);

                    if (! empty($avail['sale_from'])) {
                        $saleFrom = $this->parseDateOrTimestamp($avail['sale_from']);
                        if ($saleFrom) {
                            $fields['sales_start_at'] = $saleFrom->toDateTimeString();
                        }
                    }
                    if (! empty($avail['sale_to'])) {
                        $saleTo = $this->parseDateOrTimestamp($avail['sale_to']);
                        if ($saleTo) {
                            $fields['sales_end_at'] = $saleTo->toDateTimeString();
                        }
                    }
                    $saleSet++;
                }
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if (! $dryRun) {
                DB::table('ticket_types')->where('id', $tt->id)->update($fields);
            }
        }

        $prefix = $dryRun ? '[DRY RUN]' : 'Done.';
        $this->info("{$prefix}");
        $this->info("  Deactivated (not in bilete_eveniment): {$deactivated}");
        $this->info("  Sale price set: {$saleSet}");
        $this->info("  Sold out (outofstock, info only): {$soldOut}");
        $this->info("  Skipped (no changes): {$skipped}");

        return 0;
    }

    private function loadActiveProducts(string $file): array
    {
        $map = [];
        $handle = fopen($file, 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $wpEventId = $row[0] ?? '';
            $serialized = $row[1] ?? '';

            if (! $wpEventId || empty($serialized) || $serialized === 'NULL') {
                continue;
            }

            // Fix double-escaped quotes from CSV export
            $serialized = str_replace('""', '"', $serialized);

            // Parse PHP serialized array
            $productIds = @unserialize($serialized);
            if (is_array($productIds)) {
                $map[$wpEventId] = array_map('strval', $productIds);
            }
        }

        fclose($handle);
        return $map;
    }

    private function loadProductAvailability(string $file): array
    {
        $map = [];
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            $wpProductId = $row[0] ?? '';
            if (! $wpProductId) continue;

            $map[$wpProductId] = [
                'availability_type' => $row[1] ?? '',
                'available_from'    => $row[2] ?? '',
                'available_to'      => $row[3] ?? '',
                'sale_from'         => $row[4] ?? '',
                'sale_to'           => $row[5] ?? '',
                'sale_price'        => $row[6] ?? '',
                'expiration_date'   => $row[7] ?? '',
                'stock_status'      => $row[8] ?? '',
            ];
        }

        fclose($handle);
        return $map;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || $value === 'NULL') return null;
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateOrTimestamp(?string $value): ?Carbon
    {
        if (! $value || $value === 'NULL') return null;

        // Unix timestamp (all digits)
        if (preg_match('/^\d{9,11}$/', $value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return $this->parseDate($value);
    }
}

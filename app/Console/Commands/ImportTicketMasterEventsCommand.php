<?php

namespace App\Console\Commands;

use App\Models\AffiliateEventSource;
use App\Services\TicketMasterImportService;
use Illuminate\Console\Command;

class ImportTicketMasterEventsCommand extends Command
{
    protected $signature = 'import:ticketmaster
        {--source= : Affiliate event source ID}
        {--keyword= : Search keyword}
        {--country= : Country code (US, GB, DE, etc.)}
        {--city= : City name}
        {--category= : Classification name (music, sports, arts)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--size=50 : Number of events to fetch (max 200)}';

    protected $description = 'Import events from TicketMaster Discovery API into affiliate events';

    public function handle(): int
    {
        $sourceId = $this->option('source');

        if (!$sourceId) {
            // List available sources
            $sources = AffiliateEventSource::where('status', 'active')
                ->whereNotNull('settings')
                ->get()
                ->filter(fn ($s) => !empty($s->settings['ticketmaster_api_key']));

            if ($sources->isEmpty()) {
                $this->error('No affiliate event sources with TicketMaster API key found.');
                $this->info('Create a source in the Marketplace admin and add ticketmaster_api_key to its Settings (JSON).');
                return self::FAILURE;
            }

            $this->info('Available TicketMaster sources:');
            $sources->each(fn ($s) => $this->line("  [{$s->id}] {$s->name} (marketplace: {$s->marketplace_client_id})"));
            $this->newLine();
            $this->info('Use --source=ID to select one.');
            return self::FAILURE;
        }

        $source = AffiliateEventSource::find($sourceId);
        if (!$source) {
            $this->error("Source #{$sourceId} not found.");
            return self::FAILURE;
        }

        if (empty($source->settings['ticketmaster_api_key'])) {
            $this->error("Source \"{$source->name}\" has no ticketmaster_api_key in settings.");
            return self::FAILURE;
        }

        $this->info("Importing from TicketMaster via source: {$source->name}");

        $params = ['size' => (int) $this->option('size')];

        if ($this->option('keyword')) $params['keyword'] = $this->option('keyword');
        if ($this->option('country')) $params['countryCode'] = $this->option('country');
        if ($this->option('city')) $params['city'] = $this->option('city');
        if ($this->option('category')) $params['classificationName'] = $this->option('category');

        if ($this->option('from')) {
            $params['startDateTime'] = $this->option('from') . 'T00:00:00Z';
        }
        if ($this->option('to')) {
            $params['endDateTime'] = $this->option('to') . 'T23:59:59Z';
        }

        try {
            $service = new TicketMasterImportService($source);
            $result = $service->importEvents($params);

            $this->newLine();
            $this->info("Import complete:");
            $this->line("  Imported:  {$result['imported']}");
            $this->line("  Skipped:   {$result['skipped']} (already exist)");
            $this->line("  Available: {$result['total_available']} total on TicketMaster");

            if (!empty($result['errors'])) {
                $this->newLine();
                $this->warn("Errors (" . count($result['errors']) . "):");
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}

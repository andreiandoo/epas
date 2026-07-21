<?php

namespace App\Console\Commands;

use App\Filament\Marketplace\Pages\Dashboard;
use App\Models\MarketplaceClient;
use Illuminate\Console\Command;

class WarmMarketplaceDashboard extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:warm
                            {--marketplace= : Warm only this marketplace client id}';

    /**
     * The console command description.
     */
    protected $description = 'Pre-populate the heavy marketplace dashboard caches (month commission, stats, today, featured event) so user requests never pay the cold recompute.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = MarketplaceClient::query();
        if ($only = $this->option('marketplace')) {
            $query->whereKey($only);
        }

        $clients = $query->get();
        $this->info("Warming dashboard caches for {$clients->count()} marketplace(s)...");

        foreach ($clients as $client) {
            $start = microtime(true);
            try {
                $dash = new Dashboard();
                $dash->marketplace = $client;
                $dash->warmCaches();
                $this->line("  ✓ marketplace {$client->id} in " . round(microtime(true) - $start, 2) . 's');
            } catch (\Throwable $e) {
                $this->error("  ✗ marketplace {$client->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

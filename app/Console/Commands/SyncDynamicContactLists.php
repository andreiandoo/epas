<?php

namespace App\Console\Commands;

use App\Models\MarketplaceContactList;
use Illuminate\Console\Command;

class SyncDynamicContactLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contact-lists:sync
                            {--marketplace= : Sync lists for a specific marketplace client ID}
                            {--list= : Sync a specific list by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync subscribers for all dynamic contact lists based on their rules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = MarketplaceContactList::query()
            ->where('list_type', 'dynamic')
            ->where('is_active', true)
            ->whereNotNull('rules');

        if ($marketplaceId = $this->option('marketplace')) {
            $query->where('marketplace_client_id', $marketplaceId);
        }

        if ($listId = $this->option('list')) {
            $query->where('id', $listId);
        }

        $lists = $query->get();

        if ($lists->isEmpty()) {
            $this->info('No dynamic contact lists found to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$lists->count()} dynamic list(s) to sync.");

        $totalAdded = 0;

        foreach ($lists as $list) {
            $this->line("Syncing list: {$list->name} (ID: {$list->id})...");

            try {
                $added = $list->syncSubscribers();
                $totalAdded += $added;

                if ($added > 0) {
                    $this->info("  Added {$added} new subscriber(s)");
                } else {
                    $this->line("  No new subscribers to add");
                }
            } catch (\Exception $e) {
                $this->error("  Error syncing list: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Sync complete. Total new subscribers added: {$totalAdded}");

        return self::SUCCESS;
    }
}

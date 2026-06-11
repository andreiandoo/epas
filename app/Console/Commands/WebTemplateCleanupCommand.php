<?php

namespace App\Console\Commands;

use App\Models\WebTemplateCustomization;
use Illuminate\Console\Command;

class WebTemplateCleanupCommand extends Command
{
    protected $signature = 'web-templates:cleanup
                            {--days=90 : Expire customizations older than this many days with no views}
                            {--dry-run : Show what would be cleaned without making changes}';

    protected $description = 'Expire old web template customizations and clean up stale data';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Web Template Cleanup" . ($dryRun ? ' [DRY RUN]' : ''));
        $this->line("Looking for customizations older than {$days} days...");

        // Expire customizations that have passed their expires_at date
        $expired = WebTemplateCustomization::where('status', 'active')
            ->where('expires_at', '<', now())
            ->whereNotNull('expires_at');

        $expiredCount = $expired->count();
        if (!$dryRun) {
            $expired->update(['status' => 'expired']);
        }
        $this->line("  Expired (past expiry date): {$expiredCount}");

        // Expire old drafts with no views
        $staleDrafts = WebTemplateCustomization::where('status', 'draft')
            ->where('created_at', '<', now()->subDays($days))
            ->where('viewed_count', 0);

        $staleDraftCount = $staleDrafts->count();
        if (!$dryRun) {
            $staleDrafts->update(['status' => 'expired']);
        }
        $this->line("  Stale drafts (no views, >{$days}d): {$staleDraftCount}");

        // Expire active customizations with no views in X days
        $staleActive = WebTemplateCustomization::where('status', 'active')
            ->where('created_at', '<', now()->subDays($days))
            ->where(function ($q) use ($days) {
                $q->whereNull('last_viewed_at')
                  ->orWhere('last_viewed_at', '<', now()->subDays($days));
            });

        $staleActiveCount = $staleActive->count();
        if (!$dryRun) {
            $staleActive->update(['status' => 'expired']);
        }
        $this->line("  Stale active (no recent views, >{$days}d): {$staleActiveCount}");

        $total = $expiredCount + $staleDraftCount + $staleActiveCount;
        $this->newLine();
        $this->info("Total processed: {$total} customizations" . ($dryRun ? ' (dry run, no changes made)' : ''));

        return self::SUCCESS;
    }
}

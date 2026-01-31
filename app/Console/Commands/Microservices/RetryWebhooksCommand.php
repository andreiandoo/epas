<?php

namespace App\Console\Commands\Microservices;

use Illuminate\Console\Command;
use App\Services\Webhooks\WebhookService;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry-failed';

    protected $description = 'Retry failed webhook deliveries';

    public function handle(WebhookService $webhookService): int
    {
        $this->info('Retrying failed webhook deliveries...');

        $result = $webhookService->processRetries();

        $this->info("✓ Processed {$result['processed']} deliveries");
        $this->line("  - Succeeded: {$result['succeeded']}");
        $this->line("  - Failed: {$result['failed']}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\PromoCodes\PromoCodeService;
use Illuminate\Console\Command;

class ListPromoCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:list
                            {tenant_id : The tenant ID}
                            {--status= : Filter by status (active, inactive, expired, depleted)}
                            {--type= : Filter by type (fixed, percentage)}
                            {--limit=20 : Number of codes to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List promo codes for a tenant';

    /**
     * Execute the console command.
     */
    public function handle(PromoCodeService $promoCodeService): int
    {
        $tenantId = $this->argument('tenant_id');

        $filters = [
            'status' => $this->option('status'),
            'type' => $this->option('type'),
            'limit' => (int) $this->option('limit'),
        ];

        $promoCodes = $promoCodeService->list($tenantId, array_filter($filters));

        if (empty($promoCodes)) {
            $this->info('No promo codes found.');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($promoCodes) . " promo code(s)");
        $this->newLine();

        $headers = ['Code', 'Type', 'Value', 'Applies To', 'Status', 'Usage', 'Expires'];
        $rows = [];

        foreach ($promoCodes as $code) {
            $usage = $code['usage_count'];
            if ($code['usage_limit']) {
                $usage .= ' / ' . $code['usage_limit'];
            }

            $value = $code['type'] === 'percentage' ? $code['value'] . '%' : number_format($code['value'], 2);

            $rows[] = [
                $code['code'],
                ucfirst($code['type']),
                $value,
                ucfirst($code['applies_to']),
                ucfirst($code['status']),
                $usage,
                $code['expires_at'] ?? 'Never',
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}

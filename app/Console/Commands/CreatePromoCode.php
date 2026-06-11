<?php

namespace App\Console\Commands;

use App\Services\PromoCodes\PromoCodeService;
use Illuminate\Console\Command;

class CreatePromoCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:create
                            {tenant_id : The tenant ID}
                            {--code= : Custom promo code (optional, will be auto-generated if not provided)}
                            {--name= : Internal name for the promo code}
                            {--type=percentage : Discount type (fixed or percentage)}
                            {--value= : Discount value (amount or percentage)}
                            {--applies-to=cart : What the code applies to (cart, event, ticket_type)}
                            {--event-id= : Event ID (if applies-to=event)}
                            {--min-amount= : Minimum purchase amount required}
                            {--max-discount= : Maximum discount amount (for percentage codes)}
                            {--usage-limit= : Total number of times code can be used}
                            {--expires= : Expiration date (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new promo code for a tenant';

    /**
     * Execute the console command.
     */
    public function handle(PromoCodeService $promoCodeService): int
    {
        $tenantId = $this->argument('tenant_id');

        // Validate required options
        if (!$this->option('value')) {
            $this->error('The --value option is required');
            return Command::FAILURE;
        }

        $type = $this->option('type');
        $value = (float) $this->option('value');

        // Validate type and value
        if (!in_array($type, ['fixed', 'percentage'])) {
            $this->error('Type must be either "fixed" or "percentage"');
            return Command::FAILURE;
        }

        if ($type === 'percentage' && $value > 100) {
            $this->error('Percentage value cannot exceed 100');
            return Command::FAILURE;
        }

        // Prepare data
        $data = [
            'code' => $this->option('code'),
            'name' => $this->option('name'),
            'type' => $type,
            'value' => $value,
            'applies_to' => $this->option('applies-to'),
            'event_id' => $this->option('event-id'),
            'min_purchase_amount' => $this->option('min-amount') ? (float) $this->option('min-amount') : null,
            'max_discount_amount' => $this->option('max-discount') ? (float) $this->option('max-discount') : null,
            'usage_limit' => $this->option('usage-limit') ? (int) $this->option('usage-limit') : null,
        ];

        // Handle expiration
        if ($this->option('expires')) {
            try {
                $data['expires_at'] = new \DateTime($this->option('expires'));
            } catch (\Exception $e) {
                $this->error('Invalid expiration date format. Use Y-m-d format.');
                return Command::FAILURE;
            }
        }

        try {
            $promoCode = $promoCodeService->create($tenantId, array_filter($data, fn($v) => $v !== null));

            $this->info('✓ Promo code created successfully!');
            $this->newLine();
            $this->line("Code: {$promoCode['code']}");
            $this->line("Type: {$promoCode['type']}");
            $this->line("Value: {$promoCode['value']}");
            $this->line("Applies To: {$promoCode['applies_to']}");

            if ($promoCode['expires_at']) {
                $this->line("Expires: {$promoCode['expires_at']}");
            }

            if ($promoCode['usage_limit']) {
                $this->line("Usage Limit: {$promoCode['usage_limit']}");
            }

            $this->newLine();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create promo code: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

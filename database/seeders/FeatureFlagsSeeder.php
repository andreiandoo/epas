<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureFlagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $flags = [
            [
                'key' => 'microservices.whatsapp.enabled',
                'name' => 'WhatsApp Notifications',
                'description' => 'Enable WhatsApp messaging microservice',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'microservices.efactura.enabled',
                'name' => 'eFactura (ANAF)',
                'description' => 'Enable automatic eFactura submission to ANAF',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'microservices.accounting.enabled',
                'name' => 'Accounting Connectors',
                'description' => 'Enable accounting system integrations',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'microservices.insurance.enabled',
                'name' => 'Ticket Insurance',
                'description' => 'Enable ticket insurance microservice',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'microservices.invitations.enabled',
                'name' => 'Invitations',
                'description' => 'Enable invitations (zero-value tickets) microservice',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'features.webhooks.enabled',
                'name' => 'Webhooks',
                'description' => 'Enable webhook system for tenant integrations',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'features.notifications.whatsapp',
                'name' => 'WhatsApp Notifications for System Alerts',
                'description' => 'Send system notifications via WhatsApp',
                'is_enabled' => false,
                'rollout_strategy' => 'percentage',
                'rollout_percentage' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'features.api.rate_limiting',
                'name' => 'API Rate Limiting',
                'description' => 'Enable rate limiting for API endpoints',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'features.metrics.tracking',
                'name' => 'Metrics Tracking',
                'description' => 'Track usage metrics for microservices',
                'is_enabled' => true,
                'rollout_strategy' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'features.auto_invoice_generation',
                'name' => 'Auto Invoice Generation',
                'description' => 'Automatically generate invoices via accounting connector',
                'is_enabled' => false,
                'rollout_strategy' => 'whitelist',
                'whitelist' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($flags as $flag) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $flag['key']],
                $flag
            );
        }

        $this->command->info('✓ Feature flags seeded successfully');
    }
}

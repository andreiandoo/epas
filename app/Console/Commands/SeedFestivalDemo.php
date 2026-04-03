<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\Demo\FestivalDemoSeeder;
use Illuminate\Console\Command;

class SeedFestivalDemo extends Command
{
    protected $signature = 'demo:festival {tenant_id} {--cleanup : Remove all demo data and shadow tenant}';
    protected $description = 'Seed comprehensive festival demo data into a shadow tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant #{$tenantId} not found.");
            return self::FAILURE;
        }

        if ($tenant->is_demo_shadow) {
            $this->error("Tenant #{$tenantId} is a demo shadow tenant. Use the parent tenant ID instead.");
            return self::FAILURE;
        }

        $this->info("Parent tenant: {$tenant->public_name} (#{$tenant->id})");

        $seeder = new FestivalDemoSeeder($tenantId, $this->output);

        if ($this->option('cleanup')) {
            if (! $tenant->demo_shadow_id) {
                $this->warn('No demo data found on this tenant.');
                return self::SUCCESS;
            }
            $this->warn('Cleaning up demo data and shadow tenant...');
            $seeder->cleanup();
            $this->info('Done! Demo data and shadow tenant removed.');
            return self::SUCCESS;
        }

        if ($tenant->demo_shadow_id) {
            $this->warn("Tenant already has demo data (shadow #{$tenant->demo_shadow_id}). Use --cleanup first.");
            return self::FAILURE;
        }

        $this->info('Creating shadow tenant and seeding festival demo data...');
        $seeder->run();
        $this->info('Done! Shadow tenant created with demo data.');
        $this->info("View demo data at: /admin/tenants/{$tenant->fresh()->demo_shadow_id}/edit");

        return self::SUCCESS;
    }
}

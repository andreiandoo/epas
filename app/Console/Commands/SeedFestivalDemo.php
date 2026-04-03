<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\Demo\FestivalDemoSeeder;
use Illuminate\Console\Command;

class SeedFestivalDemo extends Command
{
    protected $signature = 'demo:festival {tenant_id} {--cleanup : Remove all demo data}';
    protected $description = 'Seed comprehensive festival demo data for an existing tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant #{$tenantId} not found.");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->public_name} (#{$tenant->id})");

        $seeder = new FestivalDemoSeeder($tenantId, $this->output);

        if ($this->option('cleanup')) {
            $this->warn('Cleaning up demo data...');
            $seeder->cleanup();
            $this->info('Demo data removed.');
            return self::SUCCESS;
        }

        $this->info('Seeding festival demo data...');
        $seeder->run();
        $this->info('Done!');

        return self::SUCCESS;
    }
}

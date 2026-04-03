<?php

namespace Database\Seeders\Demo;

use App\Models\Microservice;
use App\Models\Tenant;
use App\Models\TenantMicroservice;

class DemoMicroserviceSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenant = Tenant::findOrFail($this->parent->tenantId);
        $slugs = ['cashless', 'analytics', 'crm', 'shop', 'door-sales', 'affiliate-tracking', 'efactura'];

        foreach ($slugs as $slug) {
            $ms = Microservice::where('slug', $slug)->first();
            if (! $ms) {
                $this->parent->info("  Microservice '{$slug}' not found, skipping");
                continue;
            }

            TenantMicroservice::firstOrCreate(
                ['tenant_id' => $tenant->id, 'microservice_id' => $ms->id],
                [
                    'status' => 'active',
                    'activated_at' => now(),
                    'monthly_price' => $ms->price ?? 0,
                ]
            );
        }
    }

    public function cleanup(): void
    {
        $tenant = Tenant::findOrFail($this->parent->tenantId);
        $slugs = ['cashless', 'analytics', 'crm', 'shop', 'door-sales', 'affiliate-tracking', 'efactura'];
        $msIds = Microservice::whereIn('slug', $slugs)->pluck('id');
        TenantMicroservice::where('tenant_id', $tenant->id)->whereIn('microservice_id', $msIds)->delete();
    }
}

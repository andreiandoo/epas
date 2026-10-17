<?php

namespace App\Filament\Resources\TenantSiteDemos\Pages;

use App\Filament\Resources\TenantSiteDemos\TenantSiteDemoResource;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantSiteDemo;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La creare: domeniul demo-{slug}.tics.ro al tenantului demo (autentificarea,
 * coșul și CORS-ul găsesc tenantul după host) și checkout demo pe tenant.
 */
class CreateTenantSiteDemo extends CreateRecord
{
    protected static string $resource = TenantSiteDemoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $slug = strtolower(trim((string) $data['slug']));
        unset($data['slug']);
        $base = TenantSiteDemoResource::demoBaseDomain();
        $host = 'demo-' . $slug . '.' . $base;

        if (Domain::where('domain', $host)->exists()) {
            throw ValidationException::withMessages(['data.slug' => "{$host} este deja folosit."]);
        }

        return DB::transaction(function () use ($data, $host, $slug, $base) {
            $domain = Domain::create([
                'tenant_id' => $data['tenant_id'],
                'domain' => $host,
                'is_primary' => false,
                'is_active' => true,
                'is_managed_subdomain' => true,
                'subdomain' => 'demo-' . $slug,
                'base_domain' => $base,
                'activated_at' => now(),
                'notes' => 'Demo de template (Tenants → Demo-uri site)',
            ]);

            $tenant = Tenant::find($data['tenant_id']);
            if ($tenant) {
                $settings = $tenant->settings ?? [];
                $settings['demo_checkout'] = true;
                $tenant->update(['settings' => $settings]);
            }

            return TenantSiteDemo::create($data + ['domain_id' => $domain->id]);
        });
    }
}

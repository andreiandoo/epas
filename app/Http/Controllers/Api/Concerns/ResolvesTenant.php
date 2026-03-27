<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait ResolvesTenant
{
    /**
     * Resolve tenant from request.
     * First checks if CORS middleware already resolved it, then falls back to hostname/ID lookup.
     */
    protected function resolveRequestTenant(Request $request): ?Tenant
    {
        // Reuse tenant resolved by TenantClientCors middleware (avoids duplicate DB query)
        if ($tenant = $request->attributes->get('tenant')) {
            return $tenant;
        }

        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Cache::remember(
                "domain_tenant_{$hostname}",
                now()->addMinutes(30),
                fn () => Domain::with('tenant')
                    ->where('domain', $hostname)
                    ->where('is_active', true)
                    ->first()
            );

            return $domain?->tenant;
        }

        if ($tenantId) {
            return Cache::remember(
                "tenant_{$tenantId}",
                now()->addMinutes(30),
                fn () => Tenant::find($tenantId)
            );
        }

        return null;
    }

    /**
     * Resolve tenant with domain info (for controllers that need domain_id).
     */
    protected function resolveRequestTenantWithDomain(Request $request): ?array
    {
        // Reuse from CORS middleware
        if ($request->attributes->get('tenant')) {
            return [
                'tenant' => $request->attributes->get('tenant'),
                'domain_id' => $request->attributes->get('domain')?->id,
            ];
        }

        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Cache::remember(
                "domain_tenant_{$hostname}",
                now()->addMinutes(30),
                fn () => Domain::with('tenant')
                    ->where('domain', $hostname)
                    ->where('is_active', true)
                    ->first()
            );

            if (!$domain) {
                return null;
            }

            return [
                'tenant' => $domain->tenant,
                'domain_id' => $domain->id,
            ];
        }

        if ($tenantId) {
            $tenant = Cache::remember(
                "tenant_{$tenantId}",
                now()->addMinutes(30),
                fn () => Tenant::find($tenantId)
            );

            if (!$tenant) {
                return null;
            }

            return [
                'tenant' => $tenant,
                'domain_id' => $request->query('domain'),
            ];
        }

        return null;
    }
}

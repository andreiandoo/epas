<?php

namespace App\Http\Middleware;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures the cashless microservice is active on the tenant.
 *
 * Resolves the tenant from:
 * 1. Route parameter `editionId` → FestivalEdition → tenant
 * 2. Route parameter `accountId` → CashlessAccount → tenant
 * 3. Auth user's tenant_id
 * 4. Request header `X-Tenant-Id`
 *
 * Returns 403 if tenant doesn't have the cashless microservice active.
 */
class EnsureCashlessActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if (! $tenant->hasMicroservice('cashless')) {
            return response()->json([
                'message' => 'Cashless microservice is not active for this tenant.',
            ], 403);
        }

        // Store resolved tenant for downstream use
        $request->attributes->set('cashless_tenant', $tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. From editionId route parameter
        $editionId = $request->route('editionId');
        if ($editionId) {
            $edition = FestivalEdition::find($editionId);
            if ($edition) {
                return $edition->tenant;
            }
        }

        // 2. From accountId route parameter
        $accountId = $request->route('accountId');
        if ($accountId) {
            $account = \App\Models\Cashless\CashlessAccount::find($accountId);
            if ($account) {
                return Tenant::find($account->tenant_id);
            }
        }

        // 3. From authenticated user
        $user = $request->user();
        if ($user && $user->tenant_id) {
            return Tenant::find($user->tenant_id);
        }

        // 4. From vendor guard
        $vendor = \Illuminate\Support\Facades\Auth::guard('vendor')->user();
        if ($vendor) {
            return Tenant::find($vendor->tenant_id);
        }

        // 5. From vendor_employee guard
        $employee = \Illuminate\Support\Facades\Auth::guard('vendor_employee')->user();
        if ($employee) {
            return Tenant::find($employee->tenant_id);
        }

        // 6. From header
        $tenantId = $request->header('X-Tenant-Id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }
}

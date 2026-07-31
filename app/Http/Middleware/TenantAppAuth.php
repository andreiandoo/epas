<?php

namespace App\Http\Middleware;

use App\Models\Leisure\TenantTeamMember;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant mobile-app authentication.
 *
 * Runs AFTER `auth:sanctum` (which resolves the User behind the bearer token).
 * It confirms the caller is a tenant-associated staff member, resolves the
 * active Tenant, loads the matching TenantTeamMember (for staff tokens), and
 * publishes `tenant` / `tenant_id` / `tenant_team_member` on the request so
 * controllers can scope every query by `tenant_id` and gate actions by the
 * member's permissions.
 *
 * Identity model (see App\Models\User + App\Models\Leisure\TenantTeamMember):
 *   - tenant owner  -> User(role='tenant') with an ownedTenant; token 'tenant-app'
 *   - tenant editor -> User(role='editor'/'admin') with tenant_id; token 'tenant-app'
 *   - tenant staff  -> User linked to an active TenantTeamMember; token 'tenant-staff-{id}'
 */
class TenantAppAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->deny('Autentificare necesară.', 401);
        }

        // `->tenant` accessor resolves tenant_id -> Tenant, else the owned tenant.
        $tenant = $user->tenant;

        // Staff token encodes the team-member id; owner/admin token is 'tenant-app'.
        $teamMember = null;
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $tokenName = $token?->name ?? '';

        if (str_starts_with($tokenName, 'tenant-staff-')) {
            $memberId = (int) str_replace('tenant-staff-', '', $tokenName);
            $teamMember = TenantTeamMember::where('id', $memberId)
                ->where('user_id', $user->id)
                ->first();

            if (! $teamMember || ! $teamMember->isActive()) {
                return $this->deny('Accesul acestui membru este suspendat.', 403);
            }

            // Prefer the team member's tenant when the User row has no tenant_id.
            $tenant = $tenant ?? $teamMember->tenant;
        } elseif (! in_array($user->role, ['tenant', 'admin', 'super-admin', 'editor'], true)) {
            return $this->deny('Rol fără acces la gestiunea tenantului.', 403);
        }

        if (! $tenant) {
            return $this->deny('Contul nu este asociat unui tenant.', 403);
        }

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);
        $request->attributes->set('tenant_team_member', $teamMember);

        return $next($request);
    }

    private function deny(string $message, int $code): Response
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}

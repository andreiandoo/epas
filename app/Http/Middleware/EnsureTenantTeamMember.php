<?php

namespace App\Http\Middleware;

use App\Models\Leisure\TenantTeamMember;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware: ensures the authenticated user is an active TenantTeamMember,
 * and binds the row to the request so panel pages can `auth()->user()->teamMember`.
 *
 * Used on the /operator Filament panel — denies access to anyone who is a
 * Tixello user but does NOT have an active operator role under any tenant.
 */
class EnsureTenantTeamMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('filament.operator.auth.login');
        }

        $teamMember = TenantTeamMember::query()
            ->where('user_id', $user->id)
            ->where('status', TenantTeamMember::STATUS_ACTIVE)
            ->orderByDesc('id') // pick most-recent if user is on multiple tenants
            ->first();

        if (! $teamMember) {
            abort(403, 'Nu ai drepturi de operator. Contactează administratorul tenant-ului.');
        }

        // Expose to downstream resources via a dynamic property.
        $user->setRelation('teamMember', $teamMember);
        $request->merge(['_operator_team_member_id' => $teamMember->id]);

        return $next($request);
    }
}

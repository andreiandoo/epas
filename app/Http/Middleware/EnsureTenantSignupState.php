<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panoul tenantului pentru înscrierile noi: cine n-a terminat înscrierea sau a
 * fost respins se întoarce la /inregistrare. Tenanții vechi (approval_status null)
 * și cei aprobați / în verificare trec neatinși.
 */
class EnsureTenantSignupState
{
    public function handle(Request $request, Closure $next): Response
    {
        // Demo și „Login as” din admin nu trec prin înscriere
        if (session()->has('demo_tenant_id') || session()->has('impersonated_from_user_id')) {
            return $next($request);
        }

        $status = $request->user()?->tenant?->approval_status;
        if (in_array($status, ['incomplete', 'rejected'], true)) {
            return redirect('/inregistrare');
        }

        return $next($request);
    }
}

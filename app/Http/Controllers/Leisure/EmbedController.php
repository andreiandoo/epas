<?php

namespace App\Http\Controllers\Leisure;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TicketType;
use Illuminate\Http\Request;

/**
 * Server-side rendering for the leisure embed iframe. The host page loads
 * tixello-leisure-embed.js which mounts an iframe pointing here. We return
 * a minimal HTML page that uses the tenant's branding (passed via query
 * params) and fetches availability via the public /api/leisure/* endpoints.
 *
 * Authentication: none — only tenants with tenant_type=leisure and the
 * `features.leisure.embed.enabled` flag set to true are accessible.
 */
class EmbedController extends Controller
{
    public function show(Request $request, string $tenantSlug)
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();
        abort_unless($tenant, 404);

        $type = $tenant->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant->tenant_type;
        abort_unless($type === 'leisure', 404, 'Tenant nu este de tip leisure.');

        $features = is_array($tenant->features) ? $tenant->features : [];
        $enabled = $features['leisure']['embed']['enabled'] ?? false;
        abort_unless($enabled, 403, 'Embed widget nu este activat pentru acest tenant.');

        $ticketTypes = TicketType::query()
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'price_cents', 'currency', 'service_category', 'service_duration_minutes', 'channel_pricing']);

        return response()->view('leisure.embed', [
            'tenant' => $tenant,
            'ticketTypes' => $ticketTypes,
            'theme' => $request->input('theme', 'light'),
            'accent' => $request->input('accent', '#10b981'),
            'logo' => $request->input('logo'),
            'bgImage' => $request->input('bg_image'),
            'returnUrl' => $request->input('return_url'),
            'apiBase' => url('/api/leisure'),
        ])->header('Content-Security-Policy', "frame-ancestors *");
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\TenantSite;
use App\Services\TenantSites\SiteConfigBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurația site-ului unui tenant găzduit de noi (resources/tenant-sites).
 * Site-ul o cere după host-ul pe care a venit vizitatorul și o ține în cache
 * un minut. Cu ?preview=TOKEN întoarce ciorna, nepublicată.
 */
class TenantSiteController extends Controller
{
    public function config(Request $request, SiteConfigBuilder $builder): JsonResponse
    {
        $host = strtolower(trim((string) $request->query('host', '')));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === '' || strlen($host) > 253) {
            return response()->json(['error' => 'host_missing'], 400);
        }

        $candidates = array_unique([$host, preg_replace('/^www\./', '', $host)]);
        $domain = Domain::whereIn('domain', $candidates)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('is_suspended')->orWhere('is_suspended', false))
            ->first();
        if (! $domain) {
            return response()->json(['error' => 'unknown_host'], 404);
        }

        $site = TenantSite::with('tenant')->where('tenant_id', $domain->tenant_id)->first();
        if (! $site || ! $site->tenant) {
            return response()->json(['error' => 'no_site'], 404);
        }

        $token = (string) $request->query('preview', '');
        $preview = $token !== '' && hash_equals($site->preview_token, $token);

        if (! $preview && ! $site->isPublished()) {
            return response()->json([
                'error' => 'not_published',
                'name' => $site->tenant->public_name ?: $site->tenant->name,
            ], 404);
        }

        return response()->json($builder->payload($site, $domain->domain, $preview))
            ->header('Cache-Control', $preview ? 'no-store' : 'public, max-age=60');
    }
}

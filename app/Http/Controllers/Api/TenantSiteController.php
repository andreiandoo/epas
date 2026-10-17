<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\TenantSite;
use App\Models\TenantSiteDemo;
use App\Services\TenantSites\TemplateRegistry;
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

        // Demo live al unui template (demo-*.tics.ro): template-ul vine de la demo,
        // datele de la tenantul demo; personalizarea e cea a demo-ului sau implicită.
        $demo = TenantSiteDemo::where('domain_id', $domain->id)->first();
        if ($demo && $domain->tenant) {
            $site = new TenantSite([
                'tenant_id' => $domain->tenant_id,
                'template' => $demo->template,
                'published' => $demo->data ?: $builder->defaultDraft($domain->tenant, $demo->template),
                'published_template' => $demo->template,
                'published_at' => $demo->updated_at,
                'preview_token' => '',
            ]);
            $site->setRelation('tenant', $domain->tenant);

            return response()->json($builder->payload($site, $domain->domain, false) + ['demo' => true])
                ->header('Cache-Control', 'public, max-age=60');
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

    /** Galeria de demo-uri (demo.tics.ro): template-urile publice cu demo live. */
    public function demos(TemplateRegistry $templates): JsonResponse
    {
        $assetsBase = rtrim((string) config('tenant-sites.assets_base'), '/');
        $items = TenantSiteDemo::with('domain')->where('is_listed', true)
            ->orderBy('sort')->orderBy('title')->get()
            ->filter(fn (TenantSiteDemo $d) => $d->domain && $d->domain->is_active && $templates->get($d->template))
            ->map(function (TenantSiteDemo $d) use ($templates, $assetsBase) {
                $m = $templates->get($d->template);

                return [
                    'title' => $d->title,
                    'description' => $d->description,
                    'url' => 'https://' . $d->domain->domain,
                    'template' => $d->template,
                    'template_name' => $m['name'] ?? $d->template,
                    'category' => $m['category'] ?? 'Altele',
                    'colors' => $m['theme']['colors'] ?? [],
                    'fonts' => $m['theme']['fonts'] ?? [],
                    'preview_image' => ! empty($m['preview_image'])
                        ? $assetsBase . '/tpl/' . $d->template . '/' . ltrim($m['preview_image'], '/')
                        : null,
                ];
            })->values();

        return response()->json(['demos' => $items])->header('Cache-Control', 'public, max-age=60');
    }
}

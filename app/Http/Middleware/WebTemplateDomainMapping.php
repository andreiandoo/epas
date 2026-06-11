<?php

namespace App\Http\Middleware;

use App\Enums\WebTemplateCategory;
use App\Models\WebTemplate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebTemplateDomainMapping
{
    /**
     * Map subdomain requests (e.g., teatru.tixello.ro) to the correct template preview.
     *
     * Expected formats:
     *   {category}.tixello.{tld}/{slug}/demo         → demo preview
     *   {category}.tixello.{tld}/{slug}/{token}       → customized preview
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Match pattern: {category}.tixello.{tld}
        if (!preg_match('/^(\w+)\.tixello\.(ro|com)$/', $host, $matches)) {
            return $next($request);
        }

        $subdomain = $matches[1];
        $categoryMap = [
            'organizator' => WebTemplateCategory::SimpleOrganizer,
            'marketplace' => WebTemplateCategory::Marketplace,
            'agentie' => WebTemplateCategory::ArtistAgency,
            'teatru' => WebTemplateCategory::Theater,
            'festival' => WebTemplateCategory::Festival,
            'stadion' => WebTemplateCategory::Stadium,
        ];

        if (!isset($categoryMap[$subdomain])) {
            return $next($request);
        }

        $category = $categoryMap[$subdomain];
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        if (count($segments) < 2) {
            // Show gallery filtered by this category
            $templates = WebTemplate::where('is_active', true)
                ->where('category', $category->value)
                ->orderBy('sort_order')
                ->get();

            return response()->view('web-templates.index', [
                'templates' => $templates,
            ]);
        }

        $slug = $segments[0];
        $tokenOrDemo = $segments[1] ?? null;

        $template = WebTemplate::where('slug', $slug)
            ->where('category', $category->value)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return $next($request);
        }

        if ($tokenOrDemo === 'demo') {
            return app(\App\Http\Controllers\WebTemplatePreviewController::class)->preview($slug);
        }

        return app(\App\Http\Controllers\WebTemplatePreviewController::class)
            ->customizedPreview($request, $slug, $tokenOrDemo);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PreviewProxyController extends Controller
{
    /**
     * Proxy preview requests to tenant domains.
     * This allows previewing tenant sites in an iframe without X-Frame-Options issues.
     */
    public function proxy(Request $request, string $domain, string $path = '')
    {
        // Verify user is authenticated and owns this domain
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        $domainModel = Domain::where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if (!$domainModel) {
            abort(404, 'Domain not found');
        }

        // Verify user has access to this domain
        // Super-admins and admins can preview any domain
        // Tenant users can only preview their own domains
        $isAdmin = in_array($user->role, ['super-admin', 'admin']);
        $ownsDomain = $user->tenant_id && $domainModel->tenant_id === $user->tenant_id;

        \Log::info('Preview proxy access check', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_tenant_id' => $user->tenant_id,
            'domain' => $domain,
            'domain_tenant_id' => $domainModel->tenant_id,
            'is_admin' => $isAdmin,
            'owns_domain' => $ownsDomain,
        ]);

        if (!$isAdmin && !$ownsDomain) {
            abort(403, 'Access denied - User tenant_id: ' . ($user->tenant_id ?? 'null') . ', Domain tenant_id: ' . $domainModel->tenant_id);
        }

        // Build the target URL
        $targetUrl = 'https://' . $domain . '/' . ltrim($path, '/');

        // Forward query parameters
        if ($request->getQueryString()) {
            $targetUrl .= '?' . $request->getQueryString();
        }

        try {
            // Fetch the page from the tenant domain
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => 'TixelloPreview/1.0',
                ])
                ->get($targetUrl);

            if (!$response->successful()) {
                return response()->view('errors.preview-error', [
                    'message' => 'Failed to load preview: HTTP ' . $response->status(),
                    'domain' => $domain,
                ], $response->status());
            }

            $content = $response->body();
            $contentType = $response->header('Content-Type', 'text/html');

            // For HTML content, inject base tag to fix relative URLs
            if (str_contains($contentType, 'text/html')) {
                $baseTag = '<base href="https://' . $domain . '/">';
                $content = preg_replace('/<head([^>]*)>/i', '<head$1>' . $baseTag, $content, 1);
            }

            // Return with headers that allow framing
            return response($content)
                ->header('Content-Type', $contentType)
                ->header('X-Frame-Options', 'SAMEORIGIN')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        } catch (\Exception $e) {
            \Log::error('Preview proxy error', [
                'domain' => $domain,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return response()->view('errors.preview-error', [
                'message' => 'Failed to load preview: ' . $e->getMessage(),
                'domain' => $domain,
            ], 500);
        }
    }
}

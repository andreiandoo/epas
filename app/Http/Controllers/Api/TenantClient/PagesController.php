<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantPage;
use App\PageBuilder\BlockRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    public const MICROSERVICE_SLUG = 'website-visual-editor';

    /**
     * Check if tenant has the required microservice
     */
    private function checkMicroservice(Tenant $tenant): ?JsonResponse
    {
        if (!$tenant->hasMicroservice(self::MICROSERVICE_SLUG)) {
            return response()->json([
                'success' => false,
                'message' => 'Website Visual Editor microservice is required for this feature',
                'code' => 'MICROSERVICE_REQUIRED',
            ], 403);
        }

        return null;
    }
    /**
     * Get page by slug for public display
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->getTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        $lang = $request->input('lang', $tenant->locale ?? 'en');

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $page->slug,
                'title' => $page->getTranslation('title', $lang),
                'pageType' => $page->page_type,
                'layout' => $page->layout,
                'content' => $page->page_type === TenantPage::TYPE_CONTENT
                    ? $page->getTranslation('content', $lang)
                    : null,
                'seo' => [
                    'title' => $page->seo_title,
                    'description' => $page->seo_description,
                ],
            ],
        ]);
    }

    /**
     * List all pages (admin only, requires microservice)
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->getTenantFromAuth($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check microservice access
        if ($error = $this->checkMicroservice($tenant)) {
            return $error;
        }

        $pages = TenantPage::where('tenant_id', $tenant->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('menu_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TenantPage $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'pageType' => $page->page_type,
                'isSystem' => $page->is_system,
                'isPublished' => $page->is_published,
                'menuLocation' => $page->menu_location,
                'menuOrder' => $page->menu_order,
                'updatedAt' => $page->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * Update page layout (admin only, requires microservice)
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->getTenantFromAuth($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check microservice access
        if ($error = $this->checkMicroservice($tenant)) {
            return $error;
        }

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|array',
            'layout' => 'sometimes|array',
            'layout.blocks' => 'sometimes|array',
            'content' => 'sometimes|array',
            'seo_title' => 'sometimes|nullable|string|max:255',
            'seo_description' => 'sometimes|nullable|string|max:500',
            'is_published' => 'sometimes|boolean',
            'menu_location' => 'sometimes|string|in:header,footer,none',
            'menu_order' => 'sometimes|integer|min:0',
        ]);

        // Validate blocks if present
        if (isset($validated['layout']['blocks'])) {
            foreach ($validated['layout']['blocks'] as $index => $block) {
                $errors = BlockRegistry::validate($block);
                if (!empty($errors)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Invalid block at index {$index}",
                        'errors' => $errors,
                    ], 422);
                }
            }
        }

        $page->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Page updated successfully',
            'data' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'layout' => $page->layout,
            ],
        ]);
    }

    /**
     * Create a new page (admin only, requires microservice)
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $this->getTenantFromAuth($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check microservice access
        if ($error = $this->checkMicroservice($tenant)) {
            return $error;
        }

        $validated = $request->validate([
            'title' => 'required|array',
            'slug' => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
            'page_type' => 'required|in:content,builder',
            'layout' => 'sometimes|array',
            'content' => 'sometimes|array',
            'is_published' => 'sometimes|boolean',
            'menu_location' => 'sometimes|string|in:header,footer,none',
            'menu_order' => 'sometimes|integer|min:0',
        ]);

        // Check for duplicate slug
        $exists = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A page with this slug already exists',
            ], 422);
        }

        $page = TenantPage::create([
            'tenant_id' => $tenant->id,
            ...$validated,
            'is_system' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Page created successfully',
            'data' => [
                'id' => $page->id,
                'slug' => $page->slug,
            ],
        ], 201);
    }

    /**
     * Delete a page (admin only, non-system pages only, requires microservice)
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->getTenantFromAuth($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check microservice access
        if ($error = $this->checkMicroservice($tenant)) {
            return $error;
        }

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found',
            ], 404);
        }

        if ($page->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'System pages cannot be deleted',
            ], 403);
        }

        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Page deleted successfully',
        ]);
    }

    /**
     * Get available blocks for the page builder
     */
    public function blocks(): JsonResponse
    {
        BlockRegistry::registerDefaults();

        return response()->json([
            'success' => true,
            'data' => BlockRegistry::getPickerData(),
        ]);
    }

    /**
     * Get tenant from hostname
     */
    private function getTenant(Request $request): ?Tenant
    {
        $hostname = $request->input('hostname') ?? $request->getHost();

        $domain = Domain::where('domain', $hostname)
            ->where('is_active', true)
            ->first();

        return $domain?->tenant;
    }

    /**
     * Get tenant from authenticated user
     */
    private function getTenantFromAuth(Request $request): ?Tenant
    {
        $user = $request->user();
        return $user?->tenant;
    }
}

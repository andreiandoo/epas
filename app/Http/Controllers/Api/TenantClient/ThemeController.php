<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Services\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Get theme configuration for a tenant
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $config = ThemeService::getFullConfig($tenant);

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Get available fonts
     */
    public function fonts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ThemeService::getAvailableFonts(),
        ]);
    }

    /**
     * Update theme configuration (admin only)
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = $this->getTenantFromAuth($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'theme' => 'required|array',
            'theme.colors' => 'sometimes|array',
            'theme.typography' => 'sometimes|array',
            'theme.spacing' => 'sometimes|array',
            'theme.borders' => 'sometimes|array',
            'theme.shadows' => 'sometimes|array',
            'theme.header' => 'sometimes|array',
            'theme.buttons' => 'sometimes|array',
        ]);

        $errors = ThemeService::validate($validated['theme']);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], 422);
        }

        ThemeService::updateTheme($tenant, $validated['theme']);

        return response()->json([
            'success' => true,
            'message' => 'Theme updated successfully',
            'data' => ThemeService::getFullConfig($tenant),
        ]);
    }

    /**
     * Get tenant from hostname
     */
    private function getTenant(Request $request): ?Tenant
    {
        $hostname = $request->input('hostname') ?? $request->getHost();

        $domain = Domain::where('domain', $hostname)
            ->where('is_verified', true)
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

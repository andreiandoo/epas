<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Http\Controllers\Controller;
use App\Models\Leisure\TenantTeamMember;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared base for the tenant mobile-app API. Provides the standard response
 * envelope (mirrors the marketplace API) plus tenant-context and
 * permission helpers backed by TenantAppAuth's request attributes.
 */
abstract class BaseController extends Controller
{
    protected function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');
        if (! $tenant instanceof Tenant) {
            abort(403, 'Tenant context missing');
        }
        return $tenant;
    }

    protected function tenantId(Request $request): int
    {
        return (int) $this->tenant($request)->id;
    }

    /** The team member behind a staff token, or null for owner/admin tokens. */
    protected function teamMember(Request $request): ?TenantTeamMember
    {
        $member = $request->attributes->get('tenant_team_member');
        return $member instanceof TenantTeamMember ? $member : null;
    }

    /**
     * Owner/admin tokens (no team member) have full access; staff tokens are
     * gated by the member's `permissions` array (admins bypass — see
     * TenantTeamMember::hasPermission).
     */
    protected function can(Request $request, string $permission): bool
    {
        $member = $this->teamMember($request);
        return $member ? $member->hasPermission($permission) : true;
    }

    protected function requirePermission(Request $request, string $permission): void
    {
        if (! $this->can($request, $permission)) {
            abort(403, 'Permisiune insuficientă: ' . $permission);
        }
    }

    protected function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        if (! is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = ['success' => false, 'message' => $message];
        if (! empty($errors)) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $code);
    }

    /**
     * Paginated envelope. Optionally maps each item through $callback.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     */
    protected function paginated($paginator, ?callable $callback = null): JsonResponse
    {
        $items = $callback ? array_map($callback, $paginator->items()) : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}

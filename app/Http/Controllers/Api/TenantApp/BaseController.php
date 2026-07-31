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
    /**
     * Implied permissions per leisure_role. In real data the team member's
     * `permissions` column is often null and only `leisure_role` is set, so we
     * derive an operational permission set from the role. Explicit permissions
     * (or '*') on the member still take precedence.
     *
     * @var array<string,string[]>
     */
    protected const LEISURE_ROLE_PERMISSIONS = [
        'check_in' => ['tickets.scan'],
        'pos_cashier' => ['pos.checkout', 'orders.view', 'tickets.scan'],
        'pos_manager' => ['pos.checkout', 'orders.view', 'orders.refund', 'reports.view', 'tickets.scan'],
        'rental_operator' => ['rentals.start', 'rentals.end', 'tickets.scan'],
        'inventory_manager' => ['inventory.manage', 'reports.view'],
    ];

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
     * Permission gate. Owner/admin tokens (no team member) have full access.
     * Staff are allowed if: they are an admin/manager (or leisure_role=admin);
     * OR the permission is in their explicit `permissions` array (or '*');
     * OR it is implied by their `leisure_role` (permissions column is often
     * null in real data — see LEISURE_ROLE_PERMISSIONS).
     */
    protected function can(Request $request, string $permission): bool
    {
        $member = $this->teamMember($request);
        if (! $member) {
            return true; // tenant owner / editor / admin
        }

        if (in_array($member->role, ['admin', 'manager'], true) || $member->leisure_role === 'admin') {
            return true;
        }

        $perms = is_array($member->permissions) ? $member->permissions : [];
        if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
            return true;
        }

        $implied = self::LEISURE_ROLE_PERMISSIONS[$member->leisure_role] ?? [];
        return in_array($permission, $implied, true);
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

<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Models\Leisure\TenantTeamMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Tenant mobile-app authentication.
 *
 * Mirrors the marketplace organizer AuthController but authenticates a `User`
 * (email + password, Sanctum) that is tied to a Tenant either as owner
 * (role='tenant'), editor (tenant_id), or via an active TenantTeamMember.
 * The token name encodes staff membership so TenantAppAuth can recover it:
 *   - owner/admin -> 'tenant-app'
 *   - staff       -> 'tenant-staff-{teamMemberId}'
 */
class AuthController extends BaseController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::channel('security')->warning('Tenant-app login failed', [
                'email' => $email,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
            return $this->error('Email sau parolă incorecte.', 401);
        }

        // Resolve the tenant: user's own tenant, else an active team membership.
        $member = TenantTeamMember::active()->where('user_id', $user->id)->first();
        $tenant = $user->tenant ?? $member?->tenant;

        if (! $tenant) {
            return $this->error('Contul nu are acces la un tenant.', 403);
        }

        // A staff membership (non-owner) yields a permission-gated token.
        $isOwner = $user->role === 'tenant' && $user->ownedTenant?->id === $tenant->id;
        $useStaffToken = $member && ! $isOwner;

        $tokenName = $useStaffToken ? 'tenant-staff-' . $member->id : 'tenant-app';
        $token = $user->createToken($tokenName)->plainTextToken;

        return $this->success([
            'user' => $this->formatUser($user, $tenant, $useStaffToken ? $member : null),
            'token' => $token,
            'available_tenants' => $this->availableTenants($user),
        ], 'Autentificare reușită.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant($request);
        $member = $this->teamMember($request);

        return $this->success([
            'user' => $this->formatUser($user, $tenant, $member),
            'available_tenants' => $this->availableTenants($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
        return $this->success(null, 'Delogat.');
    }

    /** @return array<string,mixed> */
    protected function formatUser(User $user, Tenant $tenant, ?TenantTeamMember $member): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name ?? $tenant->public_name ?? $tenant->slug,
                'slug' => $tenant->slug,
                'type' => $tenant->tenant_type instanceof \BackedEnum
                    ? $tenant->tenant_type->value
                    : $tenant->tenant_type,
                'currency' => $tenant->currency ?? 'RON',
                'logo' => data_get($tenant->settings, 'branding.logo'),
            ],
            'team_member' => $member ? [
                'id' => $member->id,
                'role' => $member->role,
                'leisure_role' => $member->leisure_role,
                'permissions' => $member->permissions ?? [],
            ] : null,
            'is_owner' => $member === null,
        ];
    }

    /** Tenants this user can operate (owner + active memberships). */
    protected function availableTenants(User $user): array
    {
        $tenants = collect();

        if ($user->tenant) {
            $tenants->push($user->tenant);
        }
        TenantTeamMember::active()
            ->where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->each(function (TenantTeamMember $m) use ($tenants) {
                if ($m->tenant) {
                    $tenants->push($m->tenant);
                }
            });

        return $tenants
            ->unique('id')
            ->values()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name ?? $t->slug,
                'slug' => $t->slug,
                'type' => $t->tenant_type instanceof \BackedEnum ? $t->tenant_type->value : $t->tenant_type,
            ])
            ->all();
    }
}

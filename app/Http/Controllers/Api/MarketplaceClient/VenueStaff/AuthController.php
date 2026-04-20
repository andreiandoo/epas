<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueStaff;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\VenueStaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    /**
     * Login a venue staff member. Finds active staff whose tenant is a venue
     * operator and has events on the current marketplace, then matches the
     * password and issues a Sanctum token directly on the staff record.
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $candidates = VenueStaffMember::with('tenant')
            ->where('email', $validated['email'])
            ->where('status', 'active')
            ->whereNotNull('password')
            ->whereHas('tenant', function ($q) {
                $q->where('tenant_type', 'venue')
                  ->whereHas('venues');
            })
            ->get();

        $staff = $candidates->first(fn ($s) => Hash::check($validated['password'], $s->password));

        if (!$staff) {
            return $this->error('Invalid credentials', 401);
        }

        $token = $staff->createToken('venue-staff-' . $staff->id)->plainTextToken;

        return $this->success([
            'user_type' => 'venue_staff',
            'venue_staff' => $this->formatStaff($staff),
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Logout (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Return current venue staff profile + tenant + venues
     */
    public function me(Request $request): JsonResponse
    {
        $staff = $request->user();

        if (!$staff instanceof VenueStaffMember) {
            return $this->error('Unauthorized', 401);
        }

        $staff->load('tenant.venues');

        return $this->success([
            'user_type' => 'venue_staff',
            'venue_staff' => $this->formatStaff($staff),
        ]);
    }

    /**
     * Shape a VenueStaffMember for the mobile app
     */
    protected function formatStaff(VenueStaffMember $staff): array
    {
        $tenant = $staff->tenant;
        $venues = $tenant ? $tenant->venues : collect();

        return [
            'id' => (string) $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role' => $staff->role,
            'role_label' => $staff->role_label,
            'permissions' => $staff->getEffectivePermissions(),
            'tenant' => $tenant ? [
                'id' => (string) $tenant->id,
                'name' => $tenant->name,
                'public_name' => $tenant->public_name ?? $tenant->name,
                'type' => $tenant->tenant_type instanceof \BackedEnum
                    ? $tenant->tenant_type->value
                    : $tenant->tenant_type,
            ] : null,
            'venues' => $venues->map(fn ($v) => [
                'id' => (string) $v->id,
                'name' => $v->name,
                'city' => $v->city ?? null,
                'address' => $v->address ?? null,
            ])->values()->toArray(),
        ];
    }
}

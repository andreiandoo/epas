<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Enums\TenantType;
use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    /**
     * Login a venue-owner user (Tixello tenant account whose tenant operates
     * a venue partnered with the current marketplace). Returns a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return $this->error('No tenant associated with this account', 403);
        }

        $tenantTypeValue = $tenant->tenant_type instanceof \BackedEnum
            ? $tenant->tenant_type->value
            : $tenant->tenant_type;

        if ($tenantTypeValue !== TenantType::Venue->value) {
            return $this->error('Tenant is not a venue operator', 403);
        }

        $partnerVenueIds = $tenant->venues()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_clients.id', $client->id))
            ->pluck('id');

        if ($partnerVenueIds->isEmpty()) {
            return $this->error('Your venue is not a partner of this marketplace', 403);
        }

        $token = $user->createToken('venue-owner-' . $user->id)->plainTextToken;

        return $this->success([
            'user_type' => 'venue_owner',
            'venue_owner' => $this->formatUser($user, $tenant, $partnerVenueIds->all()),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return $this->error('Unauthorized', 401);
        }

        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant') ?: $user->tenant;

        $partnerVenueIds = $tenant->venues()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_clients.id', $client->id))
            ->pluck('id');

        return $this->success([
            'user_type' => 'venue_owner',
            'venue_owner' => $this->formatUser($user, $tenant, $partnerVenueIds->all()),
        ]);
    }

    /**
     * @param int[] $partnerVenueIds Only include venues partnered with the marketplace
     */
    protected function formatUser(User $user, ?Tenant $tenant, array $partnerVenueIds): array
    {
        $venues = $tenant
            ? $tenant->venues()->whereIn('id', $partnerVenueIds)->get()
            : collect();

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
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

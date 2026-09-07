<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Enums\TenantType;
use App\Http\Controllers\Api\MarketplaceClient\Customer\AuthController as CustomerAuthController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Unified "detect all roles" login endpoint used by the ambilet.ro
 * /autentificare page. Attempts the same email + password against all
 * three authentication realms in parallel (customer, organizer, venue
 * owner) and returns every one that matched. The frontend then either:
 *
 *   - Redirects directly (single role detected).
 *   - Renders a splash / switcher (multiple roles detected).
 *
 * Design constraints — this is the ONLY new auth-adjacent endpoint,
 * chosen to be surgical:
 *
 *   1. The three existing per-realm login controllers stay untouched.
 *      Any consumer that hits /customer/login, /organizer/login, or
 *      /venue-owner/login directly behaves identically to before.
 *
 *   2. All rejection cases (bad password, no rows, tenant/venue
 *      partnership missing, suspended, etc.) collapse into a single
 *      401 without disclosing WHICH realm rejected — an attacker who
 *      guesses one realm's password shouldn't get free confirmation
 *      that no other realm exists for the same email.
 *
 *   3. Customer 2FA still triggers exactly as it does on /customer/login
 *      — we surface the challenge in the response so the frontend can
 *      redirect to the same 2FA flow. Organizer and venue-owner don't
 *      have 2FA, so those tokens come back plain in the same payload.
 *
 *   4. Tokens are issued fresh here. Token labels are namespaced per
 *      realm so token listings on /cont/setari (customer) or the
 *      analog organizer/venue-owner surfaces stay attributable.
 */
class MultiAuthController extends BaseController
{
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Normalise the email the same way /customer/login does — mobile
        // keyboards auto-capitalize, iOS often tacks on a trailing space.
        $email = mb_strtolower(trim($validated['email']));
        $password = $validated['password'];

        $roles = [];

        // ─── 1) Customer realm ──────────────────────────────────────
        $customerRole = $this->tryCustomer($client, $email, $password, $request);
        if ($customerRole) {
            $roles[] = $customerRole;
        }

        // ─── 2) Organizer realm ─────────────────────────────────────
        $organizerRole = $this->tryOrganizer($client, $email, $password);
        if ($organizerRole) {
            $roles[] = $organizerRole;
        }

        // ─── 3) Venue owner realm ───────────────────────────────────
        $venueRole = $this->tryVenueOwner($client, $email, $password);
        if ($venueRole) {
            $roles[] = $venueRole;
        }

        if (empty($roles)) {
            return $this->error('Invalid credentials', 401);
        }

        // Primary role heuristic — surface the one the frontend should
        // redirect to when the user picks "continue as default". Order:
        // organizer > venue-owner > customer, because a person who's
        // both an organizer AND a customer usually logs in for the
        // organizer capabilities first. The frontend can override.
        $primary = $this->pickPrimary($roles);

        return $this->success([
            'roles'   => $roles,
            'primary' => $primary,
        ], 'Login successful');
    }

    /**
     * Match customer credentials and issue a token — mirrors the logic
     * inside Customer\AuthController::login without importing its
     * response envelope, so 2FA and suspension states flow through
     * this endpoint the same way.
     */
    private function tryCustomer($client, string $email, string $password, Request $request): ?array
    {
        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$customer) {
            return null;
        }

        $valid = false;
        if ($customer->password && Hash::check($password, $customer->password)) {
            $valid = true;
        } elseif ($customer->wp_password_hash) {
            $valid = $customer->verifyAndMigrateWpPassword($password);
        }

        if (!$valid || $customer->isSuspended()) {
            return null;
        }

        // 2FA — same challenge flow as /customer/login. Do NOT issue a
        // token here; frontend redirects to the 2FA challenge endpoint.
        if ($customer->two_factor_secret && $customer->two_factor_confirmed_at) {
            $challenge = Str::random(64);
            Cache::put(
                'mc_2fa_challenge:' . $challenge,
                ['customer_id' => $customer->id, 'created_at' => now()->toIso8601String()],
                now()->addMinutes(5)
            );
            return [
                'type'         => 'customer',
                'requires_2fa' => true,
                'challenge'    => $challenge,
                'token'        => null,
                'display_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->email,
            ];
        }

        $customer->recordLogin();
        $tokenName = CustomerAuthController::buildSessionTokenName($request);
        $token = $customer->createToken($tokenName)->plainTextToken;

        return [
            'type'         => 'customer',
            'requires_2fa' => false,
            'challenge'    => null,
            'token'        => $token,
            'display_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->email,
        ];
    }

    /**
     * Match organizer credentials. Unlike the customer realm, organizer
     * emails are global-unique (not per marketplace_client_id), but
     * we still limit the returned role to organizers belonging to the
     * calling client — an Ambilet-issued token can't authenticate an
     * organizer that belongs to a different marketplace.
     */
    private function tryOrganizer($client, string $email, string $password): ?array
    {
        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$organizer || !Hash::check($password, $organizer->password)) {
            return null;
        }

        if (method_exists($organizer, 'isSuspended') && $organizer->isSuspended()) {
            return null;
        }

        $token = $organizer->createToken('organizer-api')->plainTextToken;

        return [
            'type'         => 'organizer',
            'requires_2fa' => false,
            'challenge'    => null,
            'token'        => $token,
            'display_name' => $organizer->company_name ?: $organizer->name ?: $organizer->email,
        ];
    }

    /**
     * Match venue-owner credentials — must also pass all guards from
     * VenueOwner\AuthController::login (tenant exists, tenant_type is
     * Venue, at least one venue partnered with the calling client).
     */
    private function tryVenueOwner($client, string $email, string $password): ?array
    {
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return null;
        }

        $tenantType = $tenant->tenant_type instanceof \BackedEnum
            ? $tenant->tenant_type->value
            : $tenant->tenant_type;

        if ($tenantType !== TenantType::Venue->value) {
            return null;
        }

        $hasPartnership = $tenant->venues()
            ->partnerOfMarketplace($client->id)
            ->exists();

        if (!$hasPartnership) {
            return null;
        }

        $token = $user->createToken('venue-owner-' . $user->id)->plainTextToken;

        return [
            'type'         => 'venue-owner',
            'requires_2fa' => false,
            'challenge'    => null,
            'token'        => $token,
            'display_name' => $tenant->name ?: $user->name ?: $user->email,
        ];
    }

    private function pickPrimary(array $roles): string
    {
        $order = ['organizer', 'venue-owner', 'customer'];
        foreach ($order as $type) {
            foreach ($roles as $role) {
                if ($role['type'] === $type) {
                    return $type;
                }
            }
        }
        return $roles[0]['type'] ?? 'customer';
    }
}

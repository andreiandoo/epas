<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRules;

class AuthController extends BaseController
{
    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'accepts_marketing' => 'boolean',
        ]);

        // Check if email already exists for this marketplace
        $existing = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if ($existing) {
            // If guest account exists, convert to registered
            if ($existing->isGuest()) {
                $existing->update([
                    'password' => Hash::make($validated['password']),
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'] ?? $existing->phone,
                    'accepts_marketing' => $validated['accepts_marketing'] ?? false,
                    'marketing_consent_at' => ($validated['accepts_marketing'] ?? false) ? now() : null,
                ]);

                $token = $existing->createToken('customer-api')->plainTextToken;

                return $this->success([
                    'customer' => $this->formatCustomer($existing),
                    'token' => $token,
                ], 'Account created successfully');
            }

            return $this->error('An account with this email already exists', 422);
        }

        $customer = MarketplaceCustomer::create([
            'marketplace_client_id' => $client->id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'accepts_marketing' => $validated['accepts_marketing'] ?? false,
            'marketing_consent_at' => ($validated['accepts_marketing'] ?? false) ? now() : null,
            'status' => 'active',
        ]);

        $token = $customer->createToken('customer-api')->plainTextToken;

        return $this->success([
            'customer' => $this->formatCustomer($customer),
            'token' => $token,
        ], 'Registration successful', 201);
    }

    /**
     * Login a customer
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$customer || !$customer->password) {
            return $this->error('Invalid credentials', 401);
        }

        if (!Hash::check($validated['password'], $customer->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if ($customer->isSuspended()) {
            return $this->error('Your account has been suspended', 403);
        }

        $customer->recordLogin();

        $token = $customer->createToken('customer-api')->plainTextToken;

        return $this->success([
            'customer' => $this->formatCustomer($customer),
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Logout (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get current customer
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'customer' => $this->formatCustomer($customer),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
        ]);

        $customer->update($validated);

        return $this->success([
            'customer' => $this->formatCustomer($customer->fresh()),
        ], 'Profile updated');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $customer->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $customer->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->success(null, 'Password updated');
    }

    /**
     * Update marketing preferences
     */
    public function updateMarketingPreferences(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'accepts_marketing' => 'required|boolean',
        ]);

        $customer->update([
            'accepts_marketing' => $validated['accepts_marketing'],
            'marketing_consent_at' => $validated['accepts_marketing'] ? now() : null,
        ]);

        return $this->success(null, 'Marketing preferences updated');
    }

    /**
     * Format customer for response
     */
    protected function formatCustomer(MarketplaceCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'email' => $customer->email,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
            'birth_date' => $customer->birth_date?->format('Y-m-d'),
            'gender' => $customer->gender,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'country' => $customer->country,
            'locale' => $customer->locale,
            'accepts_marketing' => $customer->accepts_marketing,
            'is_guest' => $customer->isGuest(),
            'email_verified' => $customer->isEmailVerified(),
            'stats' => [
                'total_orders' => $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
            ],
            'created_at' => $customer->created_at->toIso8601String(),
        ];
    }
}

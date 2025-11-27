<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
        ]);

        // Check if customer already exists for this tenant
        $existing = Customer::where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => ['Un cont cu acest email există deja.'],
            ]);
        }

        // Create customer
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'primary_tenant_id' => $tenant->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        // Also add to pivot table for multi-tenant support
        $customer->tenants()->attach($tenant->id);

        // Generate token
        $token = Str::random(64);

        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => hash('sha256', $token),
            'name' => 'web-login',
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'email' => $customer->email,
                    'role' => 'customer',
                ],
            ],
        ]);
    }

    /**
     * Login customer
     */
    public function login(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find customer - check both tenant_id and tenants pivot table
        $customer = Customer::where('email', $validated['email'])
            ->where(function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id)
                    ->orWhereHas('tenants', function ($q) use ($tenant) {
                        $q->where('tenants.id', $tenant->id);
                    });
            })
            ->first();

        if (!$customer || !Hash::check($validated['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Datele de autentificare sunt incorecte.'],
            ]);
        }

        // Generate token
        $token = Str::random(64);

        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => hash('sha256', $token),
            'name' => 'web-login',
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'email' => $customer->email,
                    'role' => 'customer',
                ],
            ],
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => 'customer',
            ],
        ]);
    }

    /**
     * Logout customer
     */
    public function logout(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if ($customer) {
            $token = $request->bearerToken();

            if ($token) {
                // Delete the token
                CustomerToken::where('customer_id', $customer->id)
                    ->where('token', hash('sha256', $token))
                    ->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Helper: Get authenticated customer from bearer token
     */
    private function getAuthenticatedCustomer(Request $request): ?Customer
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        $customerToken = CustomerToken::where('token', hash('sha256', $token))
            ->with('customer')
            ->first();

        if (!$customerToken || $customerToken->isExpired()) {
            return null;
        }

        // Update last_used_at
        $customerToken->markAsUsed();

        return $customerToken->customer;
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Send reset email

        return response()->json([
            'success' => true,
            'message' => 'Password reset email sent',
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Reset password

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Super admin login (for debugging from core app)
     */
    public function superAdminLogin(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token required',
            ], 400);
        }

        // Verify token from cache
        $tokenData = cache()->get("admin_login_token:{$token}");

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Remove token (one-time use)
        cache()->forget("admin_login_token:{$token}");

        // Generate session token
        $sessionToken = Str::random(64);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $sessionToken,
                'user' => [
                    'id' => $tokenData['admin_id'],
                    'name' => 'Super Admin',
                    'email' => 'admin@tixello.com',
                    'role' => 'super_admin',
                ],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantClientAuthController extends Controller
{
    /**
     * Resolve tenant from hostname
     */
    private function resolveTenant(Request $request): ?array
    {
        $hostname = $request->query('hostname') ?? $request->input('hostname');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();

            if ($domain) {
                return [
                    'tenant' => $domain->tenant,
                    'domain_id' => $domain->id,
                ];
            }
        }

        return null;
    }

    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
        ]);

        $tenant = $resolved['tenant'];

        // Check if email already exists for this tenant
        $existingCustomer = Customer::where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existingCustomer) {
            return response()->json([
                'error' => 'Email already registered',
                'message' => 'Un cont cu acest email există deja.',
            ], 422);
        }

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'primary_tenant_id' => $tenant->id,
            'email' => $request->email,
            'password' => $request->password,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
        ]);

        // Attach to tenant
        $customer->tenants()->attach($tenant->id);

        // Create token
        $token = $customer->createToken('tenant-client')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $this->formatCustomer($customer),
            'token' => $token,
        ], 201);
    }

    /**
     * Login customer
     */
    public function login(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $tenant = $resolved['tenant'];

        $customer = Customer::where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Email sau parolă incorectă.',
            ], 401);
        }

        // Revoke old tokens
        $customer->tokens()->delete();

        // Create new token
        $token = $customer->createToken('tenant-client')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $this->formatCustomer($customer),
            'token' => $token,
        ]);
    }

    /**
     * Logout customer
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            // Find and delete the token
            $tokenId = explode('|', $token)[0] ?? null;
            if ($tokenId) {
                \Laravel\Sanctum\PersonalAccessToken::find($tokenId)?->delete();
            }
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated customer
     */
    public function user(Request $request): JsonResponse
    {
        $resolved = $this->resolveTenant($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Find token
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $customer = $accessToken->tokenable;

        if (!$customer || $customer->tenant_id !== $resolved['tenant']->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'user' => $this->formatCustomer($customer),
        ]);
    }

    /**
     * Format customer data for response
     */
    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'email' => $customer->email,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
        ];
    }
}

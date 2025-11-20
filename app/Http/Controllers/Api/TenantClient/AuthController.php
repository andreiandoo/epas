<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
        ]);

        // Check if customer already exists for this tenant
        // $existing = Customer::where('tenant_id', $tenant->id)->where('email', $validated['email'])->first()

        // Create customer
        // $customer = Customer::create([...])

        // Generate token
        $token = Str::random(64);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => 1, // $customer->id
                    'name' => $validated['name'],
                    'email' => $validated['email'],
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

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find customer
        // $customer = Customer::where('tenant_id', $tenant->id)->where('email', $validated['email'])->first()
        // if (!$customer || !Hash::check($validated['password'], $customer->password)) { ... }

        // Generate token
        $token = Str::random(64);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => 1,
                    'name' => 'Test User',
                    'email' => $validated['email'],
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
        // Get user from token
        // $customer = auth('customer')->user()

        return response()->json([
            'success' => true,
            'data' => [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'customer',
            ],
        ]);
    }

    /**
     * Logout customer
     */
    public function logout(Request $request): JsonResponse
    {
        // Invalidate token

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
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

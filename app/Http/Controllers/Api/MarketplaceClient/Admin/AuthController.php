<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplacePasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = MarketplaceAdmin::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if (!$admin->isActive()) {
            return $this->error('Account is suspended', 403);
        }

        // Record login
        $admin->recordLogin($request->ip());

        // Create token
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        Log::channel('marketplace')->info('Admin logged in', [
            'admin_id' => $admin->id,
            'client_id' => $client->id,
            'ip' => $request->ip(),
        ]);

        return $this->success([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'permissions' => $admin->permissions ?? [],
            ],
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin instanceof MarketplaceAdmin) {
            $admin->currentAccessToken()->delete();
        }

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get current admin
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        return $this->success([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'role' => $admin->role,
                'permissions' => $admin->permissions ?? [],
                'locale' => $admin->locale,
                'timezone' => $admin->timezone,
                'email_verified' => $admin->hasVerifiedEmail(),
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
            ],
            'marketplace' => [
                'id' => $admin->marketplaceClient->id,
                'name' => $admin->marketplaceClient->name,
                'domain' => $admin->marketplaceClient->domain,
            ],
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'locale' => 'nullable|string|in:ro,en,de,fr,es',
            'timezone' => 'nullable|string|max:50',
        ]);

        $admin->update($validated);

        return $this->success([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'locale' => $admin->locale,
                'timezone' => $admin->timezone,
            ],
        ], 'Profile updated');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $admin->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $admin->update(['password' => Hash::make($validated['password'])]);

        return $this->success(null, 'Password updated');
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $admin = MarketplaceAdmin::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if ($admin) {
            $token = Str::random(64);

            MarketplacePasswordReset::updateOrCreate(
                [
                    'email' => $admin->email,
                    'user_type' => 'admin',
                ],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // TODO: Send password reset email
            Log::channel('marketplace')->info('Admin password reset requested', [
                'admin_id' => $admin->id,
                'token' => $token, // Remove in production
            ]);
        }

        return $this->success(null, 'If the email exists, a reset link has been sent');
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $reset = MarketplacePasswordReset::where('email', $validated['email'])
            ->where('user_type', 'admin')
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        if (!$reset || !Hash::check($validated['token'], $reset->token)) {
            return $this->error('Invalid or expired reset token', 400);
        }

        $admin = MarketplaceAdmin::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$admin) {
            return $this->error('Admin not found', 404);
        }

        $admin->update(['password' => Hash::make($validated['password'])]);
        $reset->delete();

        return $this->success(null, 'Password has been reset');
    }

    /**
     * List all admins (super_admin only)
     */
    public function listAdmins(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('admins.view')) {
            return $this->error('Unauthorized', 403);
        }

        $admins = MarketplaceAdmin::where('marketplace_client_id', $admin->marketplace_client_id)
            ->orderBy('name')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'email' => $a->email,
                    'role' => $a->role,
                    'status' => $a->status,
                    'last_login_at' => $a->last_login_at?->toIso8601String(),
                    'created_at' => $a->created_at->toIso8601String(),
                ];
            });

        return $this->success(['admins' => $admins]);
    }

    /**
     * Create new admin (super_admin only)
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('admins.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,moderator',
            'permissions' => 'nullable|array',
        ]);

        // Check if email exists
        $exists = MarketplaceAdmin::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            return $this->error('Email already exists', 422);
        }

        $newAdmin = MarketplaceAdmin::create([
            'marketplace_client_id' => $admin->marketplace_client_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $validated['permissions'] ?? [],
            'status' => 'active',
        ]);

        return $this->success([
            'admin' => [
                'id' => $newAdmin->id,
                'name' => $newAdmin->name,
                'email' => $newAdmin->email,
                'role' => $newAdmin->role,
            ],
        ], 'Admin created', 201);
    }

    /**
     * Update admin (super_admin only)
     */
    public function updateAdmin(Request $request, int $adminId): JsonResponse
    {
        $currentAdmin = $this->requireAdmin($request);

        if (!$currentAdmin->hasPermission('admins.manage')) {
            return $this->error('Unauthorized', 403);
        }

        $targetAdmin = MarketplaceAdmin::where('marketplace_client_id', $currentAdmin->marketplace_client_id)
            ->where('id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return $this->error('Admin not found', 404);
        }

        // Cannot modify super_admin unless you are super_admin
        if ($targetAdmin->isSuperAdmin() && !$currentAdmin->isSuperAdmin()) {
            return $this->error('Cannot modify super admin', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|in:admin,moderator',
            'permissions' => 'nullable|array',
            'status' => 'sometimes|in:active,suspended',
        ]);

        $targetAdmin->update($validated);

        return $this->success([
            'admin' => [
                'id' => $targetAdmin->id,
                'name' => $targetAdmin->name,
                'email' => $targetAdmin->email,
                'role' => $targetAdmin->role,
                'status' => $targetAdmin->status,
            ],
        ], 'Admin updated');
    }

    /**
     * Delete admin (super_admin only)
     */
    public function deleteAdmin(Request $request, int $adminId): JsonResponse
    {
        $currentAdmin = $this->requireAdmin($request);

        if (!$currentAdmin->hasPermission('admins.manage')) {
            return $this->error('Unauthorized', 403);
        }

        if ($currentAdmin->id === $adminId) {
            return $this->error('Cannot delete yourself', 400);
        }

        $targetAdmin = MarketplaceAdmin::where('marketplace_client_id', $currentAdmin->marketplace_client_id)
            ->where('id', $adminId)
            ->first();

        if (!$targetAdmin) {
            return $this->error('Admin not found', 404);
        }

        if ($targetAdmin->isSuperAdmin()) {
            return $this->error('Cannot delete super admin', 403);
        }

        $targetAdmin->delete();

        return $this->success(null, 'Admin deleted');
    }

    /**
     * Require authenticated admin
     */
    protected function requireAdmin(Request $request): MarketplaceAdmin
    {
        $admin = $request->user();

        if (!$admin instanceof MarketplaceAdmin) {
            abort(401, 'Unauthorized');
        }

        return $admin;
    }
}

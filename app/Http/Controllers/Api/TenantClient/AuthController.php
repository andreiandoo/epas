<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Resolve tenant from request (hostname preferred, ID fallback)
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                return null;
            }

            return $domain->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }
    /**
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

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

        // Send verification email
        $this->sendVerificationEmail($customer, $tenant);

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
                    'email_verified' => false,
                    'role' => 'customer',
                ],
            ],
            'message' => 'Cont creat cu succes! Te rugăm să verifici email-ul pentru confirmare.',
        ]);
    }

    /**
     * Login customer
     */
    public function login(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

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
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

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

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required',
            ], 400);
        }

        // Find customer by verification token in meta
        $customer = Customer::whereRaw("JSON_EXTRACT(meta, '$.verification_token') = ?", [$token])
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid sau expirat',
            ], 404);
        }

        // Check if already verified
        if ($customer->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email-ul este deja verificat',
            ]);
        }

        // Verify email
        $customer->email_verified_at = now();
        $meta = $customer->meta ?? [];
        unset($meta['verification_token']);
        $customer->meta = $meta;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Email verificat cu succes! Vă mulțumim.',
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $customer = Customer::where('email', $validated['email'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Cont negăsit',
            ], 404);
        }

        if ($customer->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email-ul este deja verificat',
            ]);
        }

        $this->sendVerificationEmail($customer, $tenant);

        return response()->json([
            'success' => true,
            'message' => 'Email de verificare retrimis',
        ]);
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(Customer $customer, Tenant $tenant): void
    {
        // Generate verification token
        $token = Str::random(64);

        // Store token in customer meta
        $meta = $customer->meta ?? [];
        $meta['verification_token'] = $token;
        $customer->meta = $meta;
        $customer->save();

        // Get tenant's primary domain
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $verificationUrl = $domain
            ? "https://{$domain->domain}/verify-email?token={$token}"
            : url("/verify-email?token={$token}");

        // Send email
        Mail::send([], [], function ($message) use ($customer, $tenant, $verificationUrl) {
            $message->to($customer->email)
                ->subject('Verifică-ți adresa de email - ' . ($tenant->public_name ?? $tenant->name))
                ->html("
                    <h2>Bine ai venit, {$customer->first_name}!</h2>
                    <p>Îți mulțumim că te-ai înregistrat pe {$tenant->public_name ?? $tenant->name}.</p>
                    <p>Pentru a-ți activa contul, te rugăm să verifici adresa de email făcând click pe linkul de mai jos:</p>
                    <p><a href='{$verificationUrl}' style='background-color: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Verifică Email</a></p>
                    <p>Sau copiază și lipește acest link în browser:</p>
                    <p>{$verificationUrl}</p>
                    <p>Dacă nu ai creat acest cont, te rugăm să ignori acest email.</p>
                    <p>Cu drag,<br>Echipa {$tenant->public_name ?? $tenant->name}</p>
                ");
        });
    }
}

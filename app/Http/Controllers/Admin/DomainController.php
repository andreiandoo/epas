<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    /**
     * Create a new domain for a tenant
     */
    public function store(Request $request, $tenantId)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:255|unique:domains,domain',
                'is_primary' => 'boolean',
            ]);

            $tenant = \App\Models\Tenant::findOrFail($tenantId);

            // If this domain is set as primary, unset all other primary domains for this tenant
            if ($request->input('is_primary', false)) {
                $tenant->domains()->update(['is_primary' => false]);
            }

            $domain = $tenant->domains()->create([
                'domain' => $request->input('domain'),
                'is_primary' => $request->input('is_primary', false),
                'is_active' => true, // Default to active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain added successfully',
                'domain' => $domain,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()['domain'] ?? ['Invalid data']),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Domain creation error', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add domain: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle domain active status
     */
    public function toggleActive(Request $request, $domainId)
    {
        try {
            $domain = Domain::findOrFail($domainId);

            $isActive = $request->input('is_active');
            $domain->is_active = $isActive === true || $isActive === 'true';
            $domain->save();

            return response()->json([
                'success' => true,
                'message' => 'Domain status updated successfully',
                'is_active' => $domain->is_active,
            ]);
        } catch (\Exception $e) {
            \Log::error('Domain toggle active error', [
                'domain_id' => $domainId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update domain status',
            ], 500);
        }
    }

    /**
     * Toggle domain confirmed/verified status
     */
    public function toggleConfirmed(Request $request, $domainId)
    {
        try {
            $domain = Domain::findOrFail($domainId);

            $isVerified = $request->input('is_verified');
            $shouldVerify = $isVerified === true || $isVerified === 'true';

            if ($shouldVerify) {
                // Create or update verification record
                $verification = $domain->verifications()->latest()->first();

                if ($verification) {
                    $verification->markAsVerified();
                } else {
                    // Create a new verification marked as verified
                    $domain->verifications()->create([
                        'tenant_id' => $domain->tenant_id,
                        'verification_method' => 'manual',
                        'status' => 'verified',
                        'verified_at' => now(),
                    ]);
                }
            } else {
                // Unverify - delete or update verification
                $domain->verifications()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => $shouldVerify ? 'Domain confirmed successfully' : 'Domain unconfirmed',
            ]);
        } catch (\Exception $e) {
            \Log::error('Domain toggle confirmed error', [
                'domain_id' => $domainId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update confirmation status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle domain suspended status
     */
    public function toggleSuspended(Request $request, $domainId)
    {
        try {
            $domain = Domain::findOrFail($domainId);

            $isSuspended = $request->input('is_suspended');
            $domain->is_suspended = $isSuspended === true || $isSuspended === 'true';
            $domain->save();

            return response()->json([
                'success' => true,
                'message' => $domain->is_suspended ? 'Domain suspended' : 'Domain unsuspended',
                'is_suspended' => $domain->is_suspended,
            ]);
        } catch (\Exception $e) {
            \Log::error('Domain toggle suspended error', [
                'domain_id' => $domainId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update suspended status',
            ], 500);
        }
    }

    /**
     * Login as Super Admin to tenant site
     */
    public function loginAsAdmin($tenantId, $domain)
    {
        try {
            // TODO: Implement Super Admin impersonation logic
            // This should:
            // 1. Verify current user is Super Admin
            // 2. Create a temporary auth token
            // 3. Redirect to tenant's admin panel with the token
            // 4. Log the action for audit purposes

            $tenant = \App\Models\Tenant::findOrFail($tenantId);
            $domainRecord = $tenant->domains()->where('domain', $domain)->firstOrFail();

            // For now, just redirect to the domain
            // In production, you'll need to implement proper authentication token exchange
            return redirect('https://' . $domainRecord->domain . '/admin')
                ->with('info', 'Super Admin login functionality coming soon');

        } catch (\Exception $e) {
            \Log::error('Login as admin error', [
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to login as admin');
        }
    }
}

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

            // Create verification entry for the domain
            $domain->verifications()->create([
                'tenant_id' => $tenant->id,
                'verification_method' => 'dns_txt',
                'status' => 'pending',
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
                        'verification_method' => 'dns_txt',
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
     * Delete a domain
     */
    public function destroy($domainId)
    {
        try {
            $domain = Domain::findOrFail($domainId);

            // Delete associated verifications
            $domain->verifications()->delete();

            // Delete associated packages
            $domain->packages()->delete();

            // Delete the domain
            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => 'Domain deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Domain delete error', [
                'domain_id' => $domainId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete domain: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify domain ownership
     */
    public function verify($domainId)
    {
        try {
            $domain = Domain::findOrFail($domainId);
            $verification = $domain->verifications()->latest()->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'No verification record found',
                ], 404);
            }

            // Try each verification method
            $verified = false;
            $method = null;
            $error = null;

            // Method 1: Check DNS TXT record
            $dnsRecords = @dns_get_record('_tixello-verify.' . $domain->domain, DNS_TXT);
            if ($dnsRecords) {
                foreach ($dnsRecords as $record) {
                    if (isset($record['txt']) && $record['txt'] === $verification->verification_token) {
                        $verified = true;
                        $method = 'dns_txt';
                        break;
                    }
                }
            }

            // Method 2: Check Meta Tag
            if (!$verified) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://' . $domain->domain);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $html = curl_exec($ch);
                curl_close($ch);

                if ($html && preg_match('/<meta\s+name=["\']tixello-verification["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
                    if ($matches[1] === $verification->verification_token) {
                        $verified = true;
                        $method = 'meta_tag';
                    }
                }
            }

            // Method 3: Check File Upload
            if (!$verified) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://' . $domain->domain . '/.well-known/tixello-verify.txt');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && trim($content) === $verification->verification_token) {
                    $verified = true;
                    $method = 'file_upload';
                }
            }

            if ($verified) {
                $verification->update([
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verification_method' => $method,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Domain verified successfully using ' . str_replace('_', ' ', $method),
                    'method' => $method,
                ]);
            } else {
                $verification->incrementAttempts();

                return response()->json([
                    'success' => false,
                    'message' => 'Verification failed. Please ensure you have added one of the verification methods (DNS TXT, Meta Tag, or File) correctly.',
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Domain verification error', [
                'domain_id' => $domainId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification check failed: ' . $e->getMessage(),
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

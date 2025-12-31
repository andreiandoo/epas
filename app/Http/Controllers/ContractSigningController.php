<?php

namespace App\Http\Controllers;

use App\Models\ContractVersion;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractSigningController extends Controller
{
    /**
     * View contract (marks as viewed)
     */
    public function view(Request $request, string $token)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant || !$tenant->contract_file) {
            abort(404, 'Contract not found');
        }

        // Mark as viewed if not already
        if (!$tenant->contract_viewed_at) {
            $tenant->update([
                'contract_viewed_at' => now(),
                'contract_status' => 'viewed',
            ]);

            // Update version record
            $version = $tenant->latestContractVersion;
            if ($version) {
                $version->update([
                    'viewed_at' => now(),
                    'status' => 'viewed',
                ]);
            }
        }

        return view('contracts.view', [
            'tenant' => $tenant,
            'token' => $token,
        ]);
    }

    /**
     * Stream the contract PDF
     */
    public function pdf(string $token)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant || !$tenant->contract_file) {
            abort(404, 'Contract not found');
        }

        return Storage::disk('public')->response($tenant->contract_file);
    }

    /**
     * Show signature page
     */
    public function signPage(string $token)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant || !$tenant->contract_file) {
            abort(404, 'Contract not found');
        }

        if ($tenant->contract_signed_at) {
            return redirect()->route('contract.view', $token)
                ->with('info', 'This contract has already been signed.');
        }

        return view('contracts.sign', [
            'tenant' => $tenant,
            'token' => $token,
        ]);
    }

    /**
     * Process contract signature
     */
    public function sign(Request $request, string $token)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant || !$tenant->contract_file) {
            abort(404, 'Contract not found');
        }

        if ($tenant->contract_signed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Contract has already been signed.',
            ], 400);
        }

        $request->validate([
            'signature_data' => 'required|string',
            'signer_name' => 'required|string|max:255',
            'agree_terms' => 'required|accepted',
        ]);

        // Update tenant record
        $tenant->update([
            'contract_signed_at' => now(),
            'contract_signature_ip' => $request->ip(),
            'contract_signature_data' => $request->signature_data,
            'contract_status' => 'signed',
        ]);

        // Update version record
        $version = $tenant->latestContractVersion;
        if ($version) {
            $version->update([
                'signed_at' => now(),
                'signature_ip' => $request->ip(),
                'signature_data' => $request->signature_data,
                'status' => 'signed',
                'notes' => "Signed by: {$request->signer_name}",
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contract signed successfully.',
            'redirect' => route('contract.view', $token),
        ]);
    }

    /**
     * Get contract version history
     */
    public function history(string $token)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant) {
            abort(404, 'Contract not found');
        }

        $versions = $tenant->contractVersions()->with('template')->get();

        return view('contracts.history', [
            'tenant' => $tenant,
            'versions' => $versions,
            'token' => $token,
        ]);
    }

    /**
     * Download a specific version
     */
    public function downloadVersion(string $token, int $versionId)
    {
        $tenant = $this->getTenantFromToken($token);

        if (!$tenant) {
            abort(404, 'Contract not found');
        }

        $version = $tenant->contractVersions()->where('id', $versionId)->first();

        if (!$version || !Storage::disk('public')->exists($version->file_path)) {
            abort(404, 'Version not found');
        }

        $filename = "Contract-{$version->contract_number}-v{$version->version_number}.pdf";

        return Storage::disk('public')->download($version->file_path, $filename);
    }

    /**
     * Get tenant from signed token
     */
    protected function getTenantFromToken(string $token): ?Tenant
    {
        // Decode token (simple base64 encoding of tenant_id:hash)
        $decoded = base64_decode($token);
        $parts = explode(':', $decoded);

        if (count($parts) !== 2) {
            return null;
        }

        [$tenantId, $hash] = $parts;

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return null;
        }

        // Verify hash
        $expectedHash = hash('sha256', $tenant->id . $tenant->contact_email . config('app.key'));

        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        return $tenant;
    }

    /**
     * Generate a signing token for a tenant
     */
    public static function generateToken(Tenant $tenant): string
    {
        $hash = hash('sha256', $tenant->id . $tenant->contact_email . config('app.key'));
        return base64_encode($tenant->id . ':' . $hash);
    }
}

<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Jobs\VerifyDomainJob;
use App\Models\Domain;
use App\Models\DomainVerification;
use App\Services\DomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DomainVerificationController extends Controller
{
    public function __construct(
        protected DomainVerificationService $verificationService
    ) {}

    /**
     * Initiate domain verification
     */
    public function initiate(Request $request, Domain $domain): JsonResponse
    {
        Gate::authorize('update', $domain);

        $validated = $request->validate([
            'method' => 'sometimes|in:dns_txt,meta_tag,file_upload',
        ]);

        $method = $validated['method'] ?? DomainVerification::METHOD_DNS_TXT;

        $verification = $this->verificationService->initiateVerification($domain, $method);
        $instructions = $this->verificationService->getVerificationInstructions($verification);

        return response()->json([
            'success' => true,
            'data' => [
                'verification_id' => $verification->id,
                'token' => $verification->verification_token,
                'method' => $verification->verification_method,
                'expires_at' => $verification->expires_at->toIso8601String(),
                'instructions' => $instructions,
            ],
        ]);
    }

    /**
     * Check verification status
     */
    public function status(Domain $domain): JsonResponse
    {
        Gate::authorize('view', $domain);

        $verification = $domain->latestVerification;

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'No verification initiated for this domain',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'verification_id' => $verification->id,
                'status' => $verification->status,
                'method' => $verification->verification_method,
                'is_verified' => $verification->isVerified(),
                'is_expired' => $verification->isExpired(),
                'attempts' => $verification->attempts,
                'last_attempt_at' => $verification->last_attempt_at?->toIso8601String(),
                'last_error' => $verification->last_error,
                'verified_at' => $verification->verified_at?->toIso8601String(),
                'expires_at' => $verification->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Trigger verification check
     */
    public function verify(Domain $domain): JsonResponse
    {
        Gate::authorize('update', $domain);

        $verification = $domain->latestVerification;

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'No verification initiated for this domain',
            ], 404);
        }

        if ($verification->isVerified()) {
            return response()->json([
                'success' => true,
                'message' => 'Domain is already verified',
                'data' => [
                    'status' => 'verified',
                    'verified_at' => $verification->verified_at->toIso8601String(),
                ],
            ]);
        }

        if ($verification->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification has expired. Please initiate a new verification.',
            ], 400);
        }

        // Perform immediate verification
        $result = $this->verificationService->verify($verification);

        $verification->refresh();

        return response()->json([
            'success' => $result,
            'message' => $result
                ? 'Domain verified successfully'
                : 'Verification failed. Please check your setup and try again.',
            'data' => [
                'status' => $verification->status,
                'attempts' => $verification->attempts,
                'last_error' => $verification->last_error,
                'verified_at' => $verification->verified_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get verification instructions for a specific method
     */
    public function instructions(Request $request, Domain $domain): JsonResponse
    {
        Gate::authorize('view', $domain);

        $validated = $request->validate([
            'method' => 'required|in:dns_txt,meta_tag,file_upload',
        ]);

        // Create temporary verification to get instructions
        $verification = new DomainVerification([
            'domain_id' => $domain->id,
            'tenant_id' => $domain->tenant_id,
            'verification_method' => $validated['method'],
            'verification_token' => $domain->latestVerification?->verification_token
                ?? \Illuminate\Support\Str::random(64),
        ]);

        $verification->setRelation('domain', $domain);

        $instructions = $this->verificationService->getVerificationInstructions($verification);

        return response()->json([
            'success' => true,
            'data' => $instructions,
        ]);
    }

    /**
     * Schedule background verification (for polling)
     */
    public function scheduleVerification(Domain $domain): JsonResponse
    {
        Gate::authorize('update', $domain);

        $verification = $domain->latestVerification;

        if (!$verification || $verification->isVerified() || $verification->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending verification to schedule',
            ], 400);
        }

        VerifyDomainJob::dispatch($verification);

        return response()->json([
            'success' => true,
            'message' => 'Verification scheduled',
        ]);
    }
}

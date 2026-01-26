<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Notifications\MarketplacePasswordResetNotification;
use App\Notifications\MarketplaceEmailVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;

class AuthController extends BaseController
{
    /**
     * Register a new organizer
     */
    public function register(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'company_tax_id' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
        ]);

        // Check if email already exists for this marketplace
        if (MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->exists()) {
            return $this->error('An account with this email already exists', 422);
        }

        $organizer = MarketplaceOrganizer::create([
            'marketplace_client_id' => $client->id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'contact_name' => $validated['contact_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'company_tax_id' => $validated['company_tax_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'website' => $validated['website'] ?? null,
            'status' => 'pending', // Requires approval
        ]);

        // Generate API key for the organizer
        $organizer->generateApiKey();

        // Send verification email
        $verificationToken = $organizer->generateEmailVerificationToken();
        $organizer->notify(new MarketplaceEmailVerificationNotification(
            $verificationToken,
            'organizer',
            $client->domain
        ));

        // Generate token
        $token = $organizer->createToken('organizer-api')->plainTextToken;

        return $this->success([
            'organizer' => $this->formatOrganizer($organizer),
            'token' => $token,
            'requires_verification' => true,
            'message' => 'Registration successful. Please verify your email. Your account will then be pending approval.',
        ], 'Registration successful', 201);
    }

    /**
     * Login an organizer
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$organizer || !Hash::check($validated['password'], $organizer->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if ($organizer->isSuspended()) {
            return $this->error('Your account has been suspended', 403);
        }

        // Generate token
        $token = $organizer->createToken('organizer-api')->plainTextToken;

        return $this->success([
            'organizer' => $this->formatOrganizer($organizer),
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
     * Get current organizer
     */
    public function me(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'organizer' => $this->formatOrganizer($organizer),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'company_tax_id' => 'nullable|string|max:50',
            'company_registration' => 'nullable|string|max:100',
            'company_address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
            'social_links' => 'nullable|array',
        ]);

        $organizer->update($validated);

        return $this->success([
            'organizer' => $this->formatOrganizer($organizer->fresh()),
        ], 'Profile updated');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $organizer->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $organizer->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->success(null, 'Password updated');
    }

    /**
     * Update payout details
     */
    public function updatePayoutDetails(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'iban' => 'required|string|max:50',
            'swift' => 'nullable|string|max:20',
            'account_holder' => 'required|string|max:255',
        ]);

        $organizer->update([
            'payout_details' => $validated,
        ]);

        return $this->success(null, 'Payout details updated');
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        // Always return success to prevent email enumeration
        if (!$organizer) {
            return $this->success(null, 'If an account exists with this email, you will receive a password reset link.');
        }

        // Delete any existing tokens
        DB::table('marketplace_password_resets')
            ->where('email', $organizer->email)
            ->where('type', 'organizer')
            ->where('marketplace_client_id', $client->id)
            ->delete();

        // Create new token
        $token = Str::random(64);
        DB::table('marketplace_password_resets')->insert([
            'email' => $organizer->email,
            'type' => 'organizer',
            'marketplace_client_id' => $client->id,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send notification
        $organizer->notify(new MarketplacePasswordResetNotification(
            $token,
            'organizer',
            $client->domain
        ));

        return $this->success(null, 'If an account exists with this email, you will receive a password reset link.');
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        // Find the reset record
        $record = DB::table('marketplace_password_resets')
            ->where('email', $validated['email'])
            ->where('type', 'organizer')
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$record) {
            return $this->error('Invalid or expired reset token', 400);
        }

        // Check if token is expired (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('marketplace_password_resets')->where('id', $record->id)->delete();
            return $this->error('Reset token has expired', 400);
        }

        // Verify token
        if (!Hash::check($validated['token'], $record->token)) {
            return $this->error('Invalid or expired reset token', 400);
        }

        // Find and update organizer
        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$organizer) {
            return $this->error('Account not found', 404);
        }

        $organizer->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Delete the reset record
        DB::table('marketplace_password_resets')->where('id', $record->id)->delete();

        // Revoke all tokens
        $organizer->tokens()->delete();

        return $this->success(null, 'Password has been reset successfully. Please login with your new password.');
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$organizer) {
            return $this->error('Account not found', 404);
        }

        if ($organizer->isEmailVerified()) {
            return $this->success(null, 'Email is already verified');
        }

        if (!$organizer->verifyEmailWithToken($validated['token'])) {
            if ($organizer->isVerificationTokenExpired()) {
                return $this->error('Verification link has expired. Please request a new one.', 400);
            }
            return $this->error('Invalid verification token', 400);
        }

        return $this->success([
            'organizer' => $this->formatOrganizer($organizer->fresh()),
        ], 'Email verified successfully');
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        // Always return success to prevent email enumeration
        if (!$organizer) {
            return $this->success(null, 'If an account exists with this email, a verification link will be sent.');
        }

        if ($organizer->isEmailVerified()) {
            return $this->success(null, 'Email is already verified');
        }

        if (!$organizer->canResendVerification()) {
            return $this->error('Please wait before requesting another verification email', 429);
        }

        $verificationToken = $organizer->generateEmailVerificationToken();
        $organizer->notify(new MarketplaceEmailVerificationNotification(
            $verificationToken,
            'organizer',
            $client->domain
        ));

        return $this->success(null, 'Verification email sent');
    }

    /**
     * Verify CUI via ANAF
     */
    public function verifyCui(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cui' => 'required|string|max:20',
        ]);

        $anafService = app(\App\Services\AnafService::class);
        $result = $anafService->lookupByCui($validated['cui']);

        if (!$result) {
            return $this->error('CUI invalid sau nu a putut fi verificat', 422);
        }

        return $this->success([
            'company_name' => $result['company_name'] ?? null,
            'reg_com' => $result['reg_com'] ?? null,
            'address' => $result['address'] ?? null,
            'city' => $result['city'] ?? null,
            'county' => $result['state'] ?? null,
            'vat_payer' => $result['vat_payer'] ?? false,
            'is_active' => $result['is_active'] ?? false,
        ], 'Date verificate cu succes');
    }

    /**
     * Get contract/commission info
     */
    public function contract(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'commission_rate' => $organizer->getEffectiveCommissionRate(),
            'commission_mode' => $organizer->getEffectiveCommissionMode(),
            'terms' => [
                'Comisionul se aplica doar biletelor vandute',
                'Plata comisionului se face automat la procesarea platilor',
                'Nu exista costuri fixe sau abonamente lunare',
                'Decontarea se face in maxim 7 zile lucratoare dupa eveniment',
            ],
        ]);
    }

    /**
     * Get organizer's API key
     */
    public function getApiKey(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        // Generate if not exists
        if (!$organizer->hasApiKey()) {
            $organizer->generateApiKey();
            $organizer->refresh();
        }

        return $this->success([
            'api_key' => $organizer->api_key,
            'masked_key' => $organizer->getMaskedApiKey(),
            'created_at' => $organizer->updated_at->toIso8601String(),
        ], 'API key retrieved');
    }

    /**
     * Regenerate organizer's API key
     */
    public function regenerateApiKey(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $newKey = $organizer->regenerateApiKey();

        return $this->success([
            'api_key' => $newKey,
            'masked_key' => $organizer->getMaskedApiKey(),
            'regenerated_at' => now()->toIso8601String(),
        ], 'API key regenerated successfully. Please update your integrations with the new key.');
    }

    // =========================================
    // Bank Accounts
    // =========================================

    /**
     * Get organizer's bank accounts
     */
    public function getBankAccounts(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $accounts = $organizer->bankAccounts()->orderByDesc('is_primary')->orderBy('created_at')->get();

        return $this->success([
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'bank' => $a->bank_name,
                'iban' => $a->iban,
                'holder' => $a->account_holder,
                'is_primary' => $a->is_primary,
                'created_at' => $a->created_at->toIso8601String(),
            ])->toArray(),
        ]);
    }

    /**
     * Add a new bank account
     */
    public function addBankAccount(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'bank' => 'required|string|max:100',
            'iban' => 'required|string|max:34|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/',
            'holder' => 'required|string|max:255',
        ]);

        // Check if IBAN already exists for this organizer
        if ($organizer->bankAccounts()->where('iban', $validated['iban'])->exists()) {
            return $this->error('Acest IBAN este deja adaugat', 422);
        }

        // Check limit (max 5 accounts per organizer)
        if ($organizer->bankAccounts()->count() >= 5) {
            return $this->error('Numarul maxim de conturi bancare este 5', 422);
        }

        $isFirst = $organizer->bankAccounts()->count() === 0;

        $account = MarketplaceOrganizerBankAccount::create([
            'marketplace_organizer_id' => $organizer->id,
            'bank_name' => $validated['bank'],
            'iban' => strtoupper($validated['iban']),
            'account_holder' => $validated['holder'],
            'is_primary' => $isFirst, // First account is automatically primary
        ]);

        return $this->success([
            'account' => [
                'id' => $account->id,
                'bank' => $account->bank_name,
                'iban' => $account->iban,
                'holder' => $account->account_holder,
                'is_primary' => $account->is_primary,
                'created_at' => $account->created_at->toIso8601String(),
            ],
        ], 'Contul bancar a fost adaugat');
    }

    /**
     * Delete a bank account
     */
    public function deleteBankAccount(Request $request, int $accountId): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $account = $organizer->bankAccounts()->find($accountId);

        if (!$account) {
            return $this->error('Contul bancar nu a fost gasit', 404);
        }

        $wasPrimary = $account->is_primary;
        $account->delete();

        // If deleted account was primary, set the first remaining as primary
        if ($wasPrimary) {
            $newPrimary = $organizer->bankAccounts()->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return $this->success(null, 'Contul bancar a fost sters');
    }

    /**
     * Set a bank account as primary
     */
    public function setPrimaryBankAccount(Request $request, int $accountId): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $account = $organizer->bankAccounts()->find($accountId);

        if (!$account) {
            return $this->error('Contul bancar nu a fost gasit', 404);
        }

        $account->setAsPrimary();

        return $this->success(null, 'Contul bancar a fost setat ca principal');
    }

    /**
     * Format organizer for response
     */
    protected function formatOrganizer(MarketplaceOrganizer $organizer): array
    {
        return [
            'id' => $organizer->id,
            'email' => $organizer->email,
            'name' => $organizer->name,
            'slug' => $organizer->slug,
            'contact_name' => $organizer->contact_name,
            'phone' => $organizer->phone,
            'company_name' => $organizer->company_name,
            'company_tax_id' => $organizer->company_tax_id,
            'company_registration' => $organizer->company_registration,
            'company_address' => $organizer->company_address,
            'logo' => $organizer->logo_url,
            'description' => $organizer->description,
            'website' => $organizer->website,
            'social_links' => $organizer->social_links,
            'status' => $organizer->status,
            'is_verified' => $organizer->isVerified(),
            'commission_rate' => $organizer->getEffectiveCommissionRate(),
            'commission_mode' => $organizer->getEffectiveCommissionMode(),
            'stats' => [
                'total_events' => $organizer->total_events,
                'total_tickets_sold' => $organizer->total_tickets_sold,
                'total_revenue' => $organizer->total_revenue,
            ],
            'balance' => [
                'available' => (float) $organizer->available_balance,
                'pending' => (float) $organizer->pending_balance,
                'total_paid_out' => (float) $organizer->total_paid_out,
            ],
            'has_payout_details' => !empty($organizer->payout_details),
            'can_request_payout' => $organizer->hasMinimumPayoutBalance()
                && !$organizer->hasPendingPayout()
                && !empty($organizer->payout_details),
            'has_api_key' => $organizer->hasApiKey(),
            'api_key_masked' => $organizer->getMaskedApiKey(),
            'created_at' => $organizer->created_at->toIso8601String(),
        ];
    }
}

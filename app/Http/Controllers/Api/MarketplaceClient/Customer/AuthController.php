<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Gamification\CustomerPoints;
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
     * Register a new customer
     */
    public function register(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
            'accepts_marketing' => 'boolean',
            'referral_code' => 'nullable|string|max:20',
        ]);

        // Check if email already exists for this marketplace
        $existing = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if ($existing) {
            // If guest account exists, convert to registered
            if ($existing->isGuest()) {
                $existing->update([
                    'password' => Hash::make($validated['password']),
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'phone' => $validated['phone'] ?? $existing->phone,
                    'accepts_marketing' => $validated['accepts_marketing'] ?? false,
                    'marketing_consent_at' => ($validated['accepts_marketing'] ?? false) ? now() : null,
                ]);

                $token = $existing->createToken('customer-api')->plainTextToken;

                return $this->success([
                    'customer' => $this->formatCustomer($existing),
                    'token' => $token,
                ], 'Account created successfully');
            }

            return $this->error('An account with this email already exists', 422);
        }

        $customer = MarketplaceCustomer::create([
            'marketplace_client_id' => $client->id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'accepts_marketing' => $validated['accepts_marketing'] ?? false,
            'marketing_consent_at' => ($validated['accepts_marketing'] ?? false) ? now() : null,
            'status' => 'active',
        ]);

        // Track referral if code provided
        $referralInfo = null;
        if (!empty($validated['referral_code'])) {
            $referralInfo = $this->trackReferralRegistration($client->id, $customer->id, $validated['referral_code']);
        }

        // Send verification email
        $verificationToken = $customer->generateEmailVerificationToken();
        $customer->notify(new MarketplaceEmailVerificationNotification(
            $verificationToken,
            'customer',
            $client->domain
        ));

        $token = $customer->createToken('customer-api')->plainTextToken;

        $response = [
            'customer' => $this->formatCustomer($customer),
            'token' => $token,
            'requires_verification' => true,
        ];

        // Add referral info if registered through referral
        if ($referralInfo) {
            $response['referral'] = $referralInfo;
        }

        return $this->success($response, 'Registration successful. Please verify your email.', 201);
    }

    /**
     * Track referral registration
     */
    protected function trackReferralRegistration(int $clientId, int $customerId, string $code): ?array
    {
        // Find the referral code
        $referralCode = DB::table('marketplace_referral_codes')
            ->where('marketplace_client_id', $clientId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$referralCode) {
            return null;
        }

        // Don't allow self-referral
        if ($referralCode->marketplace_customer_id === $customerId) {
            return null;
        }

        // Check if this customer was already referred
        $existingReferral = DB::table('marketplace_referrals')
            ->where('marketplace_client_id', $clientId)
            ->where('referred_id', $customerId)
            ->first();

        if ($existingReferral) {
            return null;
        }

        // Create referral record
        DB::table('marketplace_referrals')->insert([
            'marketplace_client_id' => $clientId,
            'referral_code_id' => $referralCode->id,
            'referrer_id' => $referralCode->marketplace_customer_id,
            'referred_id' => $customerId,
            'status' => 'registered',
            'registered_at' => now(),
            'expires_at' => now()->addDays(30), // 30 days to convert
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update referral code stats
        DB::table('marketplace_referral_codes')
            ->where('id', $referralCode->id)
            ->increment('signups');

        // Get referrer info for notification
        $referrer = DB::table('marketplace_customers')
            ->where('id', $referralCode->marketplace_customer_id)
            ->first();

        // Get referral settings
        $settings = DB::table('marketplace_client_settings')
            ->where('marketplace_client_id', $clientId)
            ->where('key', 'referral_program')
            ->first();

        $referralSettings = $settings && $settings->value
            ? json_decode($settings->value, true)
            : ['referrer_reward' => 50, 'referred_reward' => 25, 'reward_type' => 'points'];

        return [
            'referrer_name' => $referrer ? ($referrer->first_name ?? 'Un prieten') : 'Un prieten',
            'referred_reward' => $referralSettings['referred_reward'],
            'reward_type' => $referralSettings['reward_type'],
            'message' => 'Ai fost invitat de ' . ($referrer ? $referrer->first_name : 'un prieten') . '! Vei primi ' . $referralSettings['referred_reward'] . ' puncte dupa prima comanda.',
        ];
    }

    /**
     * Login a customer
     */
    public function login(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$customer || !$customer->password) {
            return $this->error('Invalid credentials', 401);
        }

        if (!Hash::check($validated['password'], $customer->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if ($customer->isSuspended()) {
            return $this->error('Your account has been suspended', 403);
        }

        $customer->recordLogin();

        $token = $customer->createToken('customer-api')->plainTextToken;

        return $this->success([
            'customer' => $this->formatCustomer($customer),
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
     * Get current customer
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'customer' => $this->formatCustomer($customer),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
        ]);

        $customer->update($validated);

        return $this->success([
            'customer' => $this->formatCustomer($customer->fresh()),
        ], 'Profile updated');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $customer->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $customer->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $this->success(null, 'Password updated');
    }

    /**
     * Update marketing preferences
     */
    public function updateMarketingPreferences(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'accepts_marketing' => 'required|boolean',
        ]);

        $customer->update([
            'accepts_marketing' => $validated['accepts_marketing'],
            'marketing_consent_at' => $validated['accepts_marketing'] ? now() : null,
        ]);

        return $this->success(null, 'Marketing preferences updated');
    }

    /**
     * Update customer settings (notification preferences, etc.)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'accepts_marketing' => 'sometimes|boolean',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.reminders' => 'sometimes|boolean',
            'notification_preferences.newsletter' => 'sometimes|boolean',
            'notification_preferences.favorites' => 'sometimes|boolean',
            'notification_preferences.history' => 'sometimes|boolean',
            'notification_preferences.marketing' => 'sometimes|boolean',
        ]);

        $updates = [];

        // Update marketing preference if provided
        if (isset($validated['accepts_marketing'])) {
            $updates['accepts_marketing'] = $validated['accepts_marketing'];
            $updates['marketing_consent_at'] = $validated['accepts_marketing'] ? now() : null;
        }

        // Update settings JSON
        if (isset($validated['notification_preferences'])) {
            $currentSettings = $customer->settings ?? [];
            $updates['settings'] = array_merge($currentSettings, [
                'notification_preferences' => $validated['notification_preferences'],
            ]);
        }

        if (!empty($updates)) {
            $customer->update($updates);
        }

        return $this->success([
            'settings' => $customer->fresh()->settings,
            'accepts_marketing' => $customer->fresh()->accepts_marketing,
        ], 'Settings updated');
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

        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->whereNotNull('password')
            ->first();

        // Always return success to prevent email enumeration
        if (!$customer) {
            return $this->success(null, 'If an account exists with this email, you will receive a password reset link.');
        }

        // Delete any existing tokens
        DB::table('marketplace_password_resets')
            ->where('email', $customer->email)
            ->where('type', 'customer')
            ->where('marketplace_client_id', $client->id)
            ->delete();

        // Create new token
        $token = Str::random(64);
        DB::table('marketplace_password_resets')->insert([
            'email' => $customer->email,
            'type' => 'customer',
            'marketplace_client_id' => $client->id,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send notification
        $customer->notify(new MarketplacePasswordResetNotification(
            $token,
            'customer',
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
            ->where('type', 'customer')
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

        // Find and update customer
        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$customer) {
            return $this->error('Account not found', 404);
        }

        $customer->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Delete the reset record
        DB::table('marketplace_password_resets')->where('id', $record->id)->delete();

        // Revoke all tokens
        $customer->tokens()->delete();

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

        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if (!$customer) {
            return $this->error('Account not found', 404);
        }

        if ($customer->isEmailVerified()) {
            return $this->success(null, 'Email is already verified');
        }

        if (!$customer->verifyEmailWithToken($validated['token'])) {
            if ($customer->isVerificationTokenExpired()) {
                return $this->error('Verification link has expired. Please request a new one.', 400);
            }
            return $this->error('Invalid verification token', 400);
        }

        return $this->success([
            'customer' => $this->formatCustomer($customer->fresh()),
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

        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->whereNotNull('password')
            ->first();

        // Always return success to prevent email enumeration
        if (!$customer) {
            return $this->success(null, 'If an account exists with this email, a verification link will be sent.');
        }

        if ($customer->isEmailVerified()) {
            return $this->success(null, 'Email is already verified');
        }

        if (!$customer->canResendVerification()) {
            return $this->error('Please wait before requesting another verification email', 429);
        }

        $verificationToken = $customer->generateEmailVerificationToken();
        $customer->notify(new MarketplaceEmailVerificationNotification(
            $verificationToken,
            'customer',
            $client->domain
        ));

        return $this->success(null, 'Verification email sent');
    }

    /**
     * Format customer for response
     */
    protected function formatCustomer(MarketplaceCustomer $customer): array
    {
        // Get points and referral data
        $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
        $pointsBalance = $customerPoints ? $customerPoints->current_balance : 0;
        $referralCode = $customerPoints ? $customerPoints->referral_code : null;

        return [
            'id' => $customer->id,
            'email' => $customer->email,
            'name' => $customer->full_name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'full_name' => $customer->full_name,
            'phone' => $customer->phone,
            'birth_date' => $customer->birth_date?->format('Y-m-d'),
            'gender' => $customer->gender,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'country' => $customer->country,
            'locale' => $customer->locale,
            'accepts_marketing' => $customer->accepts_marketing,
            'settings' => $customer->settings,
            'is_guest' => $customer->isGuest(),
            'email_verified' => $customer->isEmailVerified(),
            'points' => $pointsBalance,
            'referral_code' => $referralCode,
            'stats' => [
                'total_orders' => $customer->total_orders,
                'total_spent' => (float) $customer->total_spent,
            ],
            'created_at' => $customer->created_at->toIso8601String(),
        ];
    }

    /**
     * Subscribe to newsletter - creates or updates a marketplace customer
     * as a guest with accepts_marketing = true
     */
    public function subscribeNewsletter(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
        ]);

        $existing = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
            ->first();

        if ($existing) {
            // Update marketing consent + optional fields
            $updateData = [
                'accepts_marketing' => true,
                'marketing_consent_at' => $existing->accepts_marketing ? $existing->marketing_consent_at : now(),
            ];

            // Set newsletter preference in settings
            $currentSettings = $existing->settings ?? [];
            $notifPrefs = $currentSettings['notification_preferences'] ?? [];
            $notifPrefs['newsletter'] = true;
            $currentSettings['notification_preferences'] = $notifPrefs;
            $updateData['settings'] = $currentSettings;

            if (!empty($validated['name']) && !$existing->first_name) {
                $nameParts = explode(' ', trim($validated['name']), 2);
                $updateData['first_name'] = $nameParts[0];
                if (isset($nameParts[1])) {
                    $updateData['last_name'] = $nameParts[1];
                }
            }

            if (!empty($validated['city']) && !$existing->city) {
                $updateData['city'] = $validated['city'];
            }

            $existing->update($updateData);

            // Send welcome email if first time subscribing
            if (!$existing->getOriginal('accepts_marketing')) {
                $existing->notify(new \App\Notifications\MarketplaceNewsletterWelcomeNotification(
                    $client->domain,
                    $client->name
                ));
            }

            return $this->success([
                'subscribed' => true,
                'is_new' => false,
            ], 'Te-ai abonat cu succes la newsletter!');
        }

        // Create new guest customer
        $createData = [
            'marketplace_client_id' => $client->id,
            'email' => $validated['email'],
            'accepts_marketing' => true,
            'marketing_consent_at' => now(),
            'status' => 'active',
            'settings' => [
                'notification_preferences' => [
                    'newsletter' => true,
                ],
            ],
        ];

        if (!empty($validated['name'])) {
            $nameParts = explode(' ', trim($validated['name']), 2);
            $createData['first_name'] = $nameParts[0];
            if (isset($nameParts[1])) {
                $createData['last_name'] = $nameParts[1];
            }
        }

        if (!empty($validated['city'])) {
            $createData['city'] = $validated['city'];
        }

        $customer = MarketplaceCustomer::create($createData);

        // Send welcome email
        $customer->notify(new \App\Notifications\MarketplaceNewsletterWelcomeNotification(
            $client->domain,
            $client->name
        ));

        return $this->success([
            'subscribed' => true,
            'is_new' => true,
        ], 'Te-ai abonat cu succes la newsletter!', 201);
    }
}

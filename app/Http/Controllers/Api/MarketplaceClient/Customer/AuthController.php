<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\ExperienceAction;
use App\Services\Gamification\ExperienceService;
use App\Notifications\MarketplacePasswordResetNotification;
use App\Notifications\MarketplaceEmailVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            try {
                $referralInfo = $this->trackReferralRegistration($client->id, $customer->id, $validated['referral_code']);
            } catch (\Exception $e) {
                \Log::channel('marketplace')->warning('Failed to track referral registration', [
                    'customer_id' => $customer->id,
                    'referral_code' => $validated['referral_code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send verification email via marketplace transport
        $verificationToken = $customer->generateEmailVerificationToken();
        try {
            $this->sendVerificationEmail($client, $customer, $verificationToken, $referralInfo);
        } catch (\Exception $e) {
            \Log::channel('marketplace')->warning('Failed to send verification email', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }

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

        // Get referral settings from marketplace_clients.settings JSON column
        $clientRecord = DB::table('marketplace_clients')->where('id', $clientId)->first();
        $clientSettings = $clientRecord && $clientRecord->settings
            ? (is_string($clientRecord->settings) ? json_decode($clientRecord->settings, true) : (array) $clientRecord->settings)
            : [];
        $referralSettings = $clientSettings['referral_program'] ?? [
            'referrer_reward' => 100, 'referred_reward' => 50, 'reward_type' => 'points',
        ];

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
        $customer = $customer->fresh();

        // Check profile completion and award XP if threshold reached
        $this->checkAndAwardProfileCompletion($customer);

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
            'profile_public' => 'sometimes|boolean',
            'billing_address' => 'sometimes|array',
            'billing_address.address' => 'sometimes|nullable|string|max:255',
            'billing_address.city' => 'sometimes|nullable|string|max:100',
            'billing_address.state' => 'sometimes|nullable|string|max:100',
            'billing_address.postal_code' => 'sometimes|nullable|string|max:20',
            'billing_address.country' => 'sometimes|nullable|string|max:2',
            'interests' => 'sometimes|array',
            'interests.event_categories' => 'sometimes|array',
            'interests.music_genres' => 'sometimes|array',
            'interests.event_types' => 'sometimes|array',
            'interests.event_types.*' => 'string|max:100',
            'interests.event_genres' => 'sometimes|array',
            'interests.event_genres.*' => 'string|max:100',
            'interests.preferred_cities' => 'sometimes|array',
            'interests.preferred_cities.*' => 'string|max:100',
            'interests.preferred_venues' => 'sometimes|array',
            'interests.preferred_venues.*' => 'integer',
            'profiling' => 'sometimes|array',
            'profiling.completed_steps' => 'sometimes|array',
            'profiling.last_modal_at' => 'sometimes|nullable|string',
        ]);

        $updates = [];

        // Update marketing preference if provided
        if (isset($validated['accepts_marketing'])) {
            $updates['accepts_marketing'] = $validated['accepts_marketing'];
            $updates['marketing_consent_at'] = $validated['accepts_marketing'] ? now() : null;
        }

        // Build settings JSON updates
        $currentSettings = $customer->settings ?? [];
        $settingsChanged = false;

        if (isset($validated['notification_preferences'])) {
            $currentSettings['notification_preferences'] = $validated['notification_preferences'];
            $settingsChanged = true;
        }

        if (isset($validated['profile_public'])) {
            $currentSettings['profile_public'] = $validated['profile_public'];
            $settingsChanged = true;
        }

        if (isset($validated['billing_address'])) {
            $currentSettings['billing_address'] = array_merge(
                $currentSettings['billing_address'] ?? [],
                $validated['billing_address']
            );
            $settingsChanged = true;
        }

        if (isset($validated['interests'])) {
            $currentSettings['interests'] = array_merge(
                $currentSettings['interests'] ?? [],
                $validated['interests']
            );
            $settingsChanged = true;
        }

        if (isset($validated['profiling'])) {
            $currentSettings['profiling'] = array_merge(
                $currentSettings['profiling'] ?? [],
                $validated['profiling']
            );
            $settingsChanged = true;
        }

        if ($settingsChanged) {
            $updates['settings'] = $currentSettings;
        }

        if (!empty($updates)) {
            $customer->update($updates);
        }

        $customer = $customer->fresh();

        // Check profile completion after interests/billing update
        $this->checkAndAwardProfileCompletion($customer);

        return $this->success([
            'customer' => $this->formatCustomer($customer->fresh()),
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

        // Include guests (imported from WP without password) so they can
        // set their password via the reset flow
        $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
            ->where('email', $validated['email'])
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

        // Send password reset email via marketplace transport
        $this->sendPasswordResetEmail($client, $customer, $token);

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

        // Find the reset record (support both normal and bulk tokens)
        $record = DB::table('marketplace_password_resets')
            ->where('email', $validated['email'])
            ->whereIn('type', ['customer', 'bulk_customer'])
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$record) {
            return $this->error('Invalid or expired reset token', 400);
        }

        // Check if token is expired — bulk tokens get 7 days, normal tokens 60 minutes
        $isBulkToken = str_starts_with($record->type, 'bulk_');
        $maxMinutes = $isBulkToken ? 10080 : 60; // 7 days vs 60 min
        if (now()->diffInMinutes($record->created_at) > $maxMinutes) {
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
        $this->sendVerificationEmail($client, $customer, $verificationToken);

        return $this->success(null, 'Verification email sent');
    }

    /**
     * Upload customer avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Delete old avatar if exists
        if ($customer->avatar && Storage::disk('public')->exists($customer->avatar)) {
            Storage::disk('public')->delete($customer->avatar);
        }

        $clientId = $customer->marketplace_client_id;
        $path = $request->file('avatar')->store(
            "marketplace/{$clientId}/avatars",
            'public'
        );

        $customer->update(['avatar' => $path]);

        return $this->success([
            'avatar_url' => Storage::disk('public')->url($path),
            'customer' => $this->formatCustomer($customer->fresh()),
        ], 'Avatar updated');
    }

    /**
     * Check profile completion and award XP if threshold reached
     */
    protected function checkAndAwardProfileCompletion(MarketplaceCustomer $customer): void
    {
        $settings = $customer->settings ?? [];

        // Already awarded
        if (!empty($settings['profile_completion_awarded'])) {
            return;
        }

        $completion = $this->calculateProfileCompletion($customer);

        if ($completion['percentage'] >= 80) {
            try {
                $experienceService = app(ExperienceService::class);
                $result = $experienceService->awardActionXpForMarketplace(
                    $customer->marketplace_client_id,
                    $customer->id,
                    ExperienceAction::ACTION_PROFILE_COMPLETE
                );

                // Only flag as awarded if XP was actually granted
                if ($result) {
                    $settings['profile_completion_awarded'] = true;
                    $customer->update(['settings' => $settings]);
                }
            } catch (\Exception $e) {
                \Log::channel('marketplace')->warning('Failed to award profile completion XP', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Calculate profile completion percentage
     */
    protected function calculateProfileCompletion(MarketplaceCustomer $customer): array
    {
        $settings = $customer->settings ?? [];
        $fields = [
            'first_name' => !empty($customer->first_name),
            'last_name' => !empty($customer->last_name),
            'phone' => !empty($customer->phone),
            'birth_date' => !empty($customer->birth_date),
            'gender' => !empty($customer->gender),
            'city' => !empty($customer->city) || !empty($settings['billing_address']['city'] ?? null),
            'state' => !empty($customer->state) || !empty($settings['billing_address']['state'] ?? null),
            'interests' => !empty($settings['interests']['event_categories'] ?? null)
                || !empty($settings['interests']['music_genres'] ?? null),
        ];

        $completed = array_filter($fields);
        $total = count($fields);

        return [
            'percentage' => $total > 0 ? round((count($completed) / $total) * 100) : 0,
            'completed' => count($completed),
            'total' => $total,
            'fields' => $fields,
        ];
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
            'avatar' => $customer->avatar ? Storage::disk('public')->url($customer->avatar) : null,
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
            'profile_completion' => $this->calculateProfileCompletion($customer),
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

            // Send welcome email if first time subscribing via marketplace transport
            if (!$existing->getOriginal('accepts_marketing')) {
                try {
                    $this->sendNewsletterWelcomeEmail($client, $existing);
                } catch (\Throwable $e) {
                    \Log::channel('marketplace')->warning('Failed to send newsletter welcome email', [
                        'customer_id' => $existing->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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

        // Send welcome email via marketplace transport
        try {
            $this->sendNewsletterWelcomeEmail($client, $customer);
        } catch (\Throwable $e) {
            \Log::channel('marketplace')->warning('Failed to send newsletter welcome email', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success([
            'subscribed' => true,
            'is_new' => true,
        ], 'Te-ai abonat cu succes la newsletter!', 201);
    }

    /**
     * Send verification email via marketplace transport.
     */
    protected function sendVerificationEmail($client, MarketplaceCustomer $customer, string $token, ?array $referralInfo = null): void
    {
        $domain = rtrim($client->domain, '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $verifyUrl = sprintf('%s/verify-email?token=%s&email=%s&type=customer', $domain, $token, urlencode($customer->email));
        $firstName = $customer->first_name ?: 'Client';
        $siteName = $client->name ?? 'bilete.online';

        // Build referral bonus section if applicable
        $referralSection = '';
        if ($referralInfo) {
            $reward = $referralInfo['referred_reward'] ?? 25;
            $referrerName = $referralInfo['referrer_name'] ?? 'un prieten';
            $referralSection = ''
                . '<div style="background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border:1px solid #bbf7d0;border-radius:12px;padding:20px;margin:0 0 20px;text-align:center">'
                . '<p style="font-size:24px;margin:0 0 8px">🎉</p>'
                . '<p style="font-size:16px;font-weight:700;color:#166534;margin:0 0 6px">Ai fost invitat de ' . htmlspecialchars($referrerName) . '!</p>'
                . '<p style="font-size:14px;color:#15803d;margin:0">Vei primi <strong>' . $reward . ' puncte bonus</strong> după prima ta comandă.</p>'
                . '<p style="font-size:13px;color:#16a34a;margin:8px 0 0">Punctele pot fi folosite ca reducere la achiziții viitoare.</p>'
                . '</div>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Bine ai venit pe ' . htmlspecialchars($siteName) . '!</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '!</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 20px">Mulțumim pentru înregistrare! Te rugăm să-ți verifici adresa de email pentru a finaliza configurarea contului.</p>'
            . $referralSection
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($verifyUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Verifică adresa de email</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul de verificare expiră în 24 de ore.</p>'
            . ($referralInfo ? ''
                . '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px;margin:20px 0 0;text-align:center">'
                . '<p style="font-size:14px;color:#1e40af;margin:0 0 8px;font-weight:600">💡 Cum funcționează punctele?</p>'
                . '<p style="font-size:13px;color:#3b82f6;margin:0 0 4px">1. Plasează prima ta comandă pe ' . htmlspecialchars($siteName) . '</p>'
                . '<p style="font-size:13px;color:#3b82f6;margin:0 0 4px">2. Primești automat ' . ($referralInfo['referred_reward'] ?? 25) . ' puncte bonus</p>'
                . '<p style="font-size:13px;color:#3b82f6;margin:0 0 4px">3. Folosește punctele ca reducere la achiziții</p>'
                . '<p style="font-size:13px;color:#3b82f6;margin:0">4. Invită prieteni și câștigă și mai multe puncte!</p>'
                . '</div>'
            : '<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;text-align:center">Dacă nu ai creat un cont, poți ignora acest email.</p>')
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $subject = $referralInfo
            ? 'Bine ai venit! Verifică emailul și primește ' . ($referralInfo['referred_reward'] ?? 25) . ' puncte bonus'
            : 'Verifică adresa de email';

        $this->sendMarketplaceEmail($client, $customer->email, $firstName, $subject, $html, [
            'marketplace_customer_id' => $customer->id,
            'template_slug' => $referralInfo ? 'referral_welcome' : 'email_verification',
        ]);
    }

    /**
     * Send password reset email via marketplace transport.
     */
    protected function sendPasswordResetEmail($client, MarketplaceCustomer $customer, string $token): void
    {
        $domain = $client->domain ? rtrim($client->domain, '/') : config('app.url');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $resetUrl = $domain . '/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $customer->email,
        ]);
        $firstName = $customer->first_name ?: 'Client';
        $siteName = $client->name ?? 'bilete.online';
        $expireMinutes = config('auth.passwords.marketplace.expire', 60);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Resetare parolă</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut ' . htmlspecialchars($firstName) . ',</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Ai primit acest email deoarece am primit o cerere de resetare a parolei pentru contul tău.</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Resetează parola</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul expiră în ' . $expireMinutes . ' de minute.</p>'
            . '<p style="font-size:13px;color:#94a3b8;margin:8px 0 0;text-align:center">Dacă nu ai solicitat resetarea parolei, nu este necesară nicio acțiune.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $customer->email, $firstName, 'Resetare parolă', $html, [
            'marketplace_customer_id' => $customer->id,
            'template_slug' => 'password_reset',
        ]);
    }

    /**
     * Send newsletter welcome email via marketplace transport.
     */
    protected function sendNewsletterWelcomeEmail($client, MarketplaceCustomer $customer): void
    {
        $firstName = $customer->first_name ?: 'Abonat';
        $siteName = $client->name ?? 'bilete.online';
        $domain = rtrim($client->domain, '/');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        $registerUrl = $domain . '/register';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Bine ai venit în comunitatea ' . htmlspecialchars($siteName) . '!</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut, ' . htmlspecialchars($firstName) . '!</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 20px">Mulțumim că te-ai abonat la newsletter-ul <strong>' . htmlspecialchars($siteName) . '</strong>! Ești acum parte dintr-o comunitate pasionată de evenimente.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 8px"><strong>Ce vei primi de la noi:</strong></p>'
            . '<ul style="font-size:14px;color:#475569;margin:0 0 20px;padding-left:20px;line-height:1.8">'
            . '<li>Evenimente noi — Fii primul care află despre concerte, festivaluri și spectacole</li>'
            . '<li>Oferte exclusive — Acces la reduceri și promoții speciale doar pentru abonați</li>'
            . '<li>Recomandări personalizate — Evenimente din orașul tău și pe gusturile tale</li>'
            . '</ul>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px"><strong>Vrei și mai mult?</strong> Creează-ți un cont gratuit și deblochează funcționalități exclusive:</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($registerUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Creează-ți cont gratuit</a>'
            . '</div>'
            . '<p style="font-size:14px;color:#94a3b8;margin:16px 0 0;text-align:center">Ne bucurăm că ești alături de noi!</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Cu drag, Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $customer->email, $firstName, "Bine ai venit în comunitatea {$siteName}!", $html, [
            'marketplace_customer_id' => $customer->id,
            'template_slug' => 'newsletter_welcome',
        ]);
    }
}

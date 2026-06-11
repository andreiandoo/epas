<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Services\MarketplaceCustomer\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * 2FA management endpoints for /cont/setari → tab „Securitate".
 *
 * Setup is a 2-step dance:
 *   POST /customer/2fa/initiate → returns secret + otpauth URL + 10 recovery codes
 *   POST /customer/2fa/confirm  → body { code: '123456' } activates 2FA on the account
 *
 * Disable / regenerate-codes require the current password to avoid a hostile
 * attacker-with-session-cookie locking out the real user.
 *
 * Login is challenge-based: `AuthController::login()` returns
 * `{requires_2fa: true, challenge: <opaque>}` instead of a token when 2FA is
 * active; the client then calls `loginVerify` with the challenge + TOTP /
 * recovery code to mint the real Sanctum token.
 */
class TwoFactorController extends BaseController
{
    public function __construct(protected TwoFactorService $tfa) {}

    public function initiate(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        if ($this->tfa->isActive($customer)) {
            return $this->error('2FA este deja activ. Dezactivează-l înainte de re-setup.', 422);
        }

        $client = $request->attributes->get('marketplace_client') ?? $customer->marketplaceClient;
        $issuer = ($client?->name ?: $client?->slug) ?: 'bilete.online';

        $data = $this->tfa->enable($customer, $issuer);

        return $this->success([
            'secret'         => $data['secret'],
            'qr_url'         => $data['qr_url'],
            'recovery_codes' => $data['recovery_codes'],
            'issuer'         => $issuer,
            'account'        => $customer->email,
        ], '2FA secret generated. Scan QR code in your authenticator app.');
    }

    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        if (! $this->tfa->confirm($customer, $validated['code'])) {
            return $this->error('Codul TOTP nu este valid. Verifică ora dispozitivului.', 422);
        }

        return $this->success([
            'two_factor_active' => true,
            'confirmed_at'      => $customer->two_factor_confirmed_at?->toIso8601String(),
        ], '2FA activat cu succes.');
    }

    public function disable(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($validated['password'], $customer->password)) {
            return $this->error('Parola curentă nu este corectă.', 422);
        }

        $this->tfa->disable($customer);

        return $this->success(['two_factor_active' => false], '2FA dezactivat.');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($validated['password'], $customer->password)) {
            return $this->error('Parola curentă nu este corectă.', 422);
        }

        if (! $this->tfa->isActive($customer)) {
            return $this->error('2FA nu este activ.', 422);
        }

        $codes = $this->tfa->regenerateRecoveryCodes($customer);

        return $this->success(['recovery_codes' => $codes], 'Codurile de recuperare au fost regenerate.');
    }

    public function status(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof MarketplaceCustomer) {
            return $this->error('Unauthorized', 401);
        }

        return $this->success([
            'two_factor_active' => $this->tfa->isActive($customer),
            'has_pending_setup' => ($customer->two_factor_secret && ! $customer->two_factor_confirmed_at),
            'confirmed_at'      => $customer->two_factor_confirmed_at?->toIso8601String(),
            'recovery_codes_remaining' => is_array($customer->two_factor_recovery_codes ?? null)
                ? count($customer->two_factor_recovery_codes)
                : 0,
        ]);
    }

    /**
     * Step 2 of the 2FA login flow. The opaque `challenge` token was returned
     * by AuthController::login() when the customer had 2FA active. Here we
     * trade it (+ a valid TOTP / recovery code) for the actual Sanctum token.
     */
    public function loginVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => 'required|string|max:80',
            'code'      => 'required|string|max:20',
        ]);

        $cacheKey = 'mc_2fa_challenge:' . $validated['challenge'];
        $payload = Cache::get($cacheKey);

        if (! $payload || empty($payload['customer_id'])) {
            return $this->error('Sesiunea de verificare a expirat. Reia autentificarea.', 401);
        }

        $customer = MarketplaceCustomer::find($payload['customer_id']);
        if (! $customer) {
            Cache::forget($cacheKey);
            return $this->error('Cont inexistent.', 401);
        }

        if (! $this->tfa->verify($customer, $validated['code'])) {
            return $this->error('Codul nu este valid.', 422);
        }

        Cache::forget($cacheKey);

        $customer->recordLogin();

        $tokenName = AuthController::buildSessionTokenName($request);
        $token = $customer->createToken($tokenName)->plainTextToken;

        return $this->success([
            'customer' => (new AuthController())->publicFormatCustomer($customer),
            'token'    => $token,
        ], 'Login successful');
    }
}

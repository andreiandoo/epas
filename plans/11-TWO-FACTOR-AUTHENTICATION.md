# Two-Factor Authentication (2FA) Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Password-only authentication is vulnerable to:
1. **Credential stuffing**: Reused passwords from data breaches
2. **Phishing attacks**: Stolen passwords via fake login pages
3. **Account takeover**: Single point of failure for security
4. **Compliance**: Many regulations require 2FA for sensitive data

### What This Feature Does
- TOTP (Time-based One-Time Password) via authenticator apps
- SMS-based verification codes as backup
- Recovery codes for account recovery
- Trusted device management
- Per-user 2FA enforcement options

---

## Technical Implementation

### 1. Package Installation

```bash
composer require pragmarx/google2fa-laravel
composer require bacon/bacon-qr-code
```

### 2. Database Migrations

```php
// 2026_01_03_000050_add_two_factor_auth.php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('two_factor_enabled')->default(false);
    $table->string('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
});

Schema::table('customers', function (Blueprint $table) {
    $table->boolean('two_factor_enabled')->default(false);
    $table->string('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
});

Schema::create('trusted_devices', function (Blueprint $table) {
    $table->id();
    $table->morphs('authenticatable');
    $table->string('device_id', 64)->unique();
    $table->string('device_name')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('trusted_until');
    $table->timestamp('last_used_at');
    $table->timestamps();

    $table->index(['authenticatable_type', 'authenticatable_id']);
});
```

### 3. Service Class

```php
// app/Services/Auth/TwoFactorAuthService.php
<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Customer;
use App\Models\TrustedDevice;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Enable 2FA for a user - returns secret and QR code
     */
    public function enable(User|Customer $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->two_factor_secret = encrypt($secret);
        $user->save();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCodeSvg = $this->generateQrCode($qrCodeUrl);

        return [
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Confirm 2FA setup with a code
     */
    public function confirm(User|Customer $user, string $code): bool
    {
        if (!$user->two_factor_secret) {
            throw new \Exception('2FA not initialized');
        }

        $secret = decrypt($user->two_factor_secret);

        if (!$this->google2fa->verifyKey($secret, $code)) {
            return false;
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return true;
    }

    /**
     * Disable 2FA
     */
    public function disable(User|Customer $user, string $code): bool
    {
        if (!$this->verify($user, $code)) {
            return false;
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        // Remove trusted devices
        TrustedDevice::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->delete();

        return true;
    }

    /**
     * Verify a 2FA code
     */
    public function verify(User|Customer $user, string $code): bool
    {
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return false;
        }

        $secret = decrypt($user->two_factor_secret);

        // Check TOTP code
        if ($this->google2fa->verifyKey($secret, $code)) {
            return true;
        }

        // Check recovery code
        return $this->useRecoveryCode($user, $code);
    }

    /**
     * Use a recovery code
     */
    public function useRecoveryCode(User|Customer $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        $index = array_search($code, $recoveryCodes);

        if ($index === false) {
            return false;
        }

        // Remove used code
        unset($recoveryCodes[$index]);

        $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
        $user->save();

        return true;
    }

    /**
     * Generate new recovery codes
     */
    public function regenerateRecoveryCodes(User|Customer $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->two_factor_recovery_codes = encrypt(json_encode($codes));
        $user->save();

        return $codes;
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRecoveryCodesCount(User|Customer $user): int
    {
        if (!$user->two_factor_recovery_codes) {
            return 0;
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        return count($codes);
    }

    /**
     * Trust a device
     */
    public function trustDevice(User|Customer $user, Request $request, int $days = 30): TrustedDevice
    {
        $deviceId = Str::random(64);

        return TrustedDevice::create([
            'authenticatable_type' => get_class($user),
            'authenticatable_id' => $user->id,
            'device_id' => $deviceId,
            'device_name' => $this->parseDeviceName($request->userAgent()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'trusted_until' => now()->addDays($days),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Check if device is trusted
     */
    public function isDeviceTrusted(User|Customer $user, string $deviceId): bool
    {
        return TrustedDevice::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('trusted_until', '>', now())
            ->exists();
    }

    /**
     * Revoke a trusted device
     */
    public function revokeDevice(User|Customer $user, string $deviceId): bool
    {
        return TrustedDevice::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->where('device_id', $deviceId)
            ->delete() > 0;
    }

    /**
     * Get trusted devices
     */
    public function getTrustedDevices(User|Customer $user): array
    {
        return TrustedDevice::where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->id)
            ->where('trusted_until', '>', now())
            ->get()
            ->map(fn($d) => [
                'id' => $d->device_id,
                'name' => $d->device_name,
                'ip' => $d->ip_address,
                'trusted_until' => $d->trusted_until,
                'last_used' => $d->last_used_at,
            ])
            ->toArray();
    }

    protected function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    protected function generateQrCode(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    protected function parseDeviceName(string $userAgent): string
    {
        if (str_contains($userAgent, 'iPhone')) return 'iPhone';
        if (str_contains($userAgent, 'iPad')) return 'iPad';
        if (str_contains($userAgent, 'Android')) return 'Android Device';
        if (str_contains($userAgent, 'Mac')) return 'Mac';
        if (str_contains($userAgent, 'Windows')) return 'Windows PC';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        return 'Unknown Device';
    }
}
```

### 4. Controller

```php
// app/Http/Controllers/Auth/TwoFactorController.php
class TwoFactorController extends Controller
{
    public function __construct(protected TwoFactorAuthService $twoFactorService) {}

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['error' => '2FA already enabled'], 400);
        }

        $result = $this->twoFactorService->enable($user);

        return response()->json([
            'secret' => $result['secret'],
            'qr_code' => $result['qr_code_svg'],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$this->twoFactorService->confirm($user, $request->code)) {
            return response()->json(['error' => 'Invalid code'], 400);
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$this->twoFactorService->disable($user, $request->code)) {
            return response()->json(['error' => 'Invalid code'], 400);
        }

        return response()->json(['disabled' => true]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'trust_device' => 'boolean',
        ]);

        $user = $request->user();

        if (!$this->twoFactorService->verify($user, $request->code)) {
            return response()->json(['error' => 'Invalid code'], 400);
        }

        $response = ['verified' => true];

        if ($request->trust_device) {
            $device = $this->twoFactorService->trustDevice($user, $request);
            $response['device_id'] = $device->device_id;
        }

        return response()->json($response);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$this->twoFactorService->verify($user, $request->code)) {
            return response()->json(['error' => 'Invalid code'], 400);
        }

        $codes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return response()->json(['recovery_codes' => $codes]);
    }

    public function trustedDevices(Request $request): JsonResponse
    {
        $devices = $this->twoFactorService->getTrustedDevices($request->user());

        return response()->json(['devices' => $devices]);
    }

    public function revokeDevice(Request $request, string $deviceId): JsonResponse
    {
        $this->twoFactorService->revokeDevice($request->user(), $deviceId);

        return response()->json(['revoked' => true]);
    }
}
```

### 5. Middleware

```php
// app/Http/Middleware/RequireTwoFactor.php
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->two_factor_enabled) {
            return $next($request);
        }

        // Check for trusted device cookie
        $deviceId = $request->cookie('trusted_device');
        if ($deviceId) {
            $service = app(TwoFactorAuthService::class);
            if ($service->isDeviceTrusted($user, $deviceId)) {
                return $next($request);
            }
        }

        // Check if 2FA verified in session
        if (session('two_factor_verified') === $user->id) {
            return $next($request);
        }

        return response()->json([
            'error' => 'Two-factor authentication required',
            'two_factor_required' => true,
        ], 403);
    }
}
```

### 6. Routes

```php
Route::middleware('auth:sanctum')->prefix('2fa')->group(function () {
    Route::post('/enable', [TwoFactorController::class, 'enable']);
    Route::post('/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/disable', [TwoFactorController::class, 'disable']);
    Route::post('/verify', [TwoFactorController::class, 'verify']);
    Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    Route::get('/devices', [TwoFactorController::class, 'trustedDevices']);
    Route::delete('/devices/{deviceId}', [TwoFactorController::class, 'revokeDevice']);
});
```

---

## Testing Checklist

1. [ ] 2FA enable generates secret and QR code
2. [ ] Confirm with valid TOTP code works
3. [ ] Invalid codes are rejected
4. [ ] Recovery codes work
5. [ ] Recovery code is consumed after use
6. [ ] Disable 2FA requires valid code
7. [ ] Trusted devices bypass 2FA
8. [ ] Device can be revoked
9. [ ] Middleware blocks unverified requests
10. [ ] Session-based verification works

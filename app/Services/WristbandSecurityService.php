<?php

namespace App\Services;

use App\Models\Wristband;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class WristbandSecurityService
{
    /**
     * Generate HMAC-signed QR payload for a wristband.
     *
     * Format: {uid}:{edition_id}:{hmac}
     * The HMAC signs uid+edition_id with a per-tenant secret.
     */
    public function generateQrPayload(Wristband $wristband): string
    {
        $uid       = $wristband->uid;
        $editionId = $wristband->festival_edition_id;
        $hmac      = $this->computeHmac($uid, $editionId, $wristband->tenant_id);

        return "{$uid}:{$editionId}:{$hmac}";
    }

    /**
     * Parse and validate a signed QR payload.
     *
     * Returns ['uid' => string, 'edition_id' => int] on success, null on failure.
     */
    public function validateQrPayload(string $payload, int $tenantId): ?array
    {
        $parts = explode(':', $payload);
        if (count($parts) !== 3) {
            return null;
        }

        [$uid, $editionId, $hmac] = $parts;
        $editionId = (int) $editionId;

        $expected = $this->computeHmac($uid, $editionId, $tenantId);

        if (! hash_equals($expected, $hmac)) {
            return null;
        }

        return ['uid' => $uid, 'edition_id' => $editionId];
    }

    /**
     * Check if a wristband transaction is rate-limited.
     * Returns true if the request should be blocked.
     */
    public function isRateLimited(string $uid, string $action = 'charge', int $windowSeconds = 10): bool
    {
        $key = "wristband_rate:{$action}:{$uid}";

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, $windowSeconds);
        return false;
    }

    /**
     * Check for concurrent usage from different POS devices (fraud detection).
     * Returns the conflicting device UID if suspicious, null otherwise.
     */
    public function detectConcurrentUsage(string $uid, ?string $posDeviceUid): ?string
    {
        if (! $posDeviceUid) {
            return null;
        }

        $key           = "wristband_last_pos:{$uid}";
        $lastDevice    = Cache::get($key);
        $lastTimestamp  = Cache::get("{$key}:ts");

        // Store current device
        Cache::put($key, $posDeviceUid, 120);       // 2 min window
        Cache::put("{$key}:ts", now()->timestamp, 120);

        // If same wristband used on different POS within 30 seconds = suspicious
        if ($lastDevice && $lastDevice !== $posDeviceUid && $lastTimestamp) {
            $elapsed = now()->timestamp - $lastTimestamp;
            if ($elapsed < 30) {
                return $lastDevice;
            }
        }

        return null;
    }

    /**
     * Validate wristband PIN if set.
     */
    public function validatePin(Wristband $wristband, ?string $pin): bool
    {
        if (! $wristband->pin_hash) {
            return true; // No PIN set, skip validation
        }

        if (! $pin) {
            return false; // PIN required but not provided
        }

        return Hash::check($pin, $wristband->pin_hash);
    }

    /**
     * Set PIN on a wristband.
     */
    public function setPin(Wristband $wristband, string $pin): void
    {
        $wristband->update([
            'pin_hash' => Hash::make($pin),
        ]);
    }

    /**
     * Compute HMAC for a wristband UID + edition.
     */
    private function computeHmac(string $uid, int $editionId, int $tenantId): string
    {
        $secret = $this->getSigningSecret($tenantId);
        $data   = "{$uid}:{$editionId}";

        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Get the signing secret for a tenant.
     * Falls back to APP_KEY if no tenant-specific secret is configured.
     */
    private function getSigningSecret(int $tenantId): string
    {
        // Tenant-specific secret can be stored in tenant settings
        $tenantSecret = Cache::remember(
            "wristband_secret:{$tenantId}",
            3600,
            fn () => \App\Models\Tenant::find($tenantId)?->settings['wristband_secret'] ?? null
        );

        return $tenantSecret ?? config('app.key');
    }
}

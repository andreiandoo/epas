<?php

namespace App\Services\MarketplaceCustomer;

use App\Models\MarketplaceCustomer;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-based 2FA helper for marketplace customers.
 *
 * Flow:
 *   1. enable()  → generate base32 secret, store on customer row,
 *                  return { secret, qr_url, recovery_codes (10) }
 *   2. confirm() → verify a TOTP code, set two_factor_confirmed_at
 *   3. disable() → wipe secret + recovery codes + confirmed_at
 *
 * Uses pragmarx/google2fa (already installed as a Filament transitive dep).
 */
class TwoFactorService
{
    protected Google2FA $g;

    public function __construct()
    {
        $this->g = new Google2FA();
    }

    /**
     * Issue a new secret + recovery codes for the customer.
     *
     * Does NOT activate 2FA yet — caller must verify the first TOTP via
     * confirm() before two_factor_confirmed_at gets set. Until then the
     * secret is stored but not enforced at login.
     *
     * @return array{secret:string, qr_url:string, recovery_codes:array<string>}
     */
    public function enable(MarketplaceCustomer $customer, string $issuer): array
    {
        $secret = $this->g->generateSecretKey(32);
        $recovery = $this->generateRecoveryCodes();

        $customer->two_factor_secret = $secret;
        $customer->two_factor_recovery_codes = $recovery;
        $customer->two_factor_confirmed_at = null;
        $customer->save();

        $qrUrl = $this->buildOtpAuthUrl($issuer, $customer->email, $secret);

        return [
            'secret'         => $secret,
            'qr_url'         => $qrUrl,
            'recovery_codes' => $recovery,
        ];
    }

    /**
     * Verify the first TOTP code and mark 2FA active on the customer.
     */
    public function confirm(MarketplaceCustomer $customer, string $code): bool
    {
        if (! $customer->two_factor_secret) {
            return false;
        }

        // 1-window tolerance = accept current and immediately previous code
        $valid = $this->g->verifyKey($customer->two_factor_secret, trim($code), 1);
        if (! $valid) return false;

        $customer->two_factor_confirmed_at = now();
        $customer->save();

        return true;
    }

    /**
     * Verify a TOTP code OR a recovery code (one-shot — recovery codes are
     * consumed). Used at login to gate access after primary credentials.
     */
    public function verify(MarketplaceCustomer $customer, string $code): bool
    {
        if (! $customer->two_factor_secret || ! $customer->two_factor_confirmed_at) {
            return false;
        }

        $code = trim($code);

        // Plain TOTP first
        if ($this->g->verifyKey($customer->two_factor_secret, $code, 1)) {
            return true;
        }

        // Recovery code (case-insensitive match against stored list)
        $codes = $customer->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(str_replace(['-', ' '], '', $code));
        foreach ($codes as $i => $rc) {
            if (strtoupper(str_replace(['-', ' '], '', $rc)) === $normalized) {
                // consume — drop the code so it can't be reused
                unset($codes[$i]);
                $customer->two_factor_recovery_codes = array_values($codes);
                $customer->save();
                return true;
            }
        }

        return false;
    }

    /**
     * Disable 2FA completely (requires password re-verify at controller level).
     */
    public function disable(MarketplaceCustomer $customer): void
    {
        $customer->two_factor_secret = null;
        $customer->two_factor_recovery_codes = null;
        $customer->two_factor_confirmed_at = null;
        $customer->save();
    }

    /**
     * Issue a fresh set of 10 recovery codes (invalidates the old ones).
     */
    public function regenerateRecoveryCodes(MarketplaceCustomer $customer): array
    {
        $codes = $this->generateRecoveryCodes();
        $customer->two_factor_recovery_codes = $codes;
        $customer->save();
        return $codes;
    }

    public function isActive(MarketplaceCustomer $customer): bool
    {
        return $customer->two_factor_secret !== null
            && $customer->two_factor_confirmed_at !== null;
    }

    // ====== internals ======

    /**
     * Format: XXXX-XXXX (8 alphanumerics with a dash). 10 codes per set.
     */
    protected function generateRecoveryCodes(int $count = 10): array
    {
        $out = [];
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // unambiguous chars
        $len = strlen($alphabet);
        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= $alphabet[random_int(0, $len - 1)];
            }
            $out[] = substr($code, 0, 4) . '-' . substr($code, 4);
        }
        return $out;
    }

    /**
     * Build the otpauth:// URL used by Google Authenticator / Authy / 1Password.
     * Returns the URL string — frontend renders the QR via a JS QR library.
     */
    protected function buildOtpAuthUrl(string $issuer, string $accountName, string $secret): string
    {
        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($accountName)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }
}

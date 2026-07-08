<?php

namespace App\Support;

/**
 * PII masking helpers used across the public API. The rule is simple:
 * anything that leaves the platform through /v1/public/analytics/*
 * must not carry raw names, emails, or phones. A caller who legitimately
 * needs to reach specific buyers goes through the (separate, scoped and
 * audit-logged) /v1/public/sales/export path.
 */
class PiiMask
{
    /**
     * Deterministic per-installation buyer identifier. Same real buyer
     * always hashes to the same string, so the caller can join across
     * multiple endpoints (e.g. superfan on /performance vs geographic
     * bucket on /audience) without knowing who the person is.
     *
     * The salt below is app-key derived so a leaked hash from another
     * environment cannot be linked to a production identity.
     */
    public static function buyerHash($buyerId): string
    {
        if (!$buyerId) {
            return 'buyer:unknown';
        }
        $salt = config('app.key', 'no-key') . ':buyer';
        return 'buyer:' . substr(hash('sha256', $salt . ':' . (string) $buyerId), 0, 16);
    }

    /**
     * Return the first initial of a name only, if provided.
     * Empty string when we have nothing useful (avoids "unknown_person"
     * strings leaking through to callers that render them).
     */
    public static function initial(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($name, 0, 1)) . '.';
    }

    /**
     * Age bucket from a birth date. Never returns the birth date itself.
     */
    public static function ageBucket($birthDate): string
    {
        if (!$birthDate) {
            return 'unknown';
        }
        try {
            $age = \Illuminate\Support\Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return 'unknown';
        }
        return match (true) {
            $age < 18 => '<18',
            $age <= 24 => '18-24',
            $age <= 34 => '25-34',
            $age <= 44 => '35-44',
            $age <= 54 => '45-54',
            default => '55+',
        };
    }
}

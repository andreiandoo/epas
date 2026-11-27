<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fusul orar de afișare al tenantului curent (panoul tenant).
 * Oglinda lui MarketplaceTz: DB rămâne în UTC, conversia se face doar la afișare.
 * Tenant.timezone null/invalid = Europe/Bucharest.
 */
class TenantTz
{
    public const DEFAULT_TIMEZONE = MarketplaceTz::DEFAULT_TIMEZONE;

    public static function tz(?Tenant $tenant = null): string
    {
        $tenant ??= self::resolveCurrent();

        return self::normalize($tenant?->timezone);
    }

    public static function fmt(
        mixed $datetime,
        string $format = 'd M Y H:i',
        ?Tenant $tenant = null,
        string $fallback = '—',
    ): string {
        if ($datetime === null || $datetime === '') {
            return $fallback;
        }

        try {
            $carbon = $datetime instanceof CarbonInterface
                ? Carbon::instance($datetime)
                : ($datetime instanceof \DateTimeInterface
                    ? Carbon::instance(\DateTime::createFromInterface($datetime))
                    : Carbon::parse($datetime));
        } catch (\Throwable $e) {
            return $fallback;
        }

        return $carbon->copy()->setTimezone(self::tz($tenant))->format($format);
    }

    public static function resolveCurrent(): ?Tenant
    {
        try {
            $user = auth()->user();
            if ($user && method_exists($user, 'tenant')) {
                return $user->tenant;
            }
        } catch (\Throwable $e) {
            // Auth nepornit (console) — cade pe default.
        }

        return null;
    }

    private static function normalize(?string $tz): string
    {
        if (! $tz) {
            return self::DEFAULT_TIMEZONE;
        }

        return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : self::DEFAULT_TIMEZONE;
    }
}

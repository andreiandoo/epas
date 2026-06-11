<?php

namespace App\Support;

use App\Models\MarketplaceClient;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Marketplace-aware timezone helper.
 *
 * The DB stores all timestamps in UTC (config/app.php = 'UTC'). This helper
 * converts to the current marketplace's timezone at DISPLAY time only — no
 * data migration, no APP_TIMEZONE change. Defaults to Europe/Bucharest so the
 * behaviour matches existing hardcoded usages while we migrate them over.
 *
 * Usage:
 *
 *   {{ \App\Support\MarketplaceTz::fmt($order->created_at, 'd M Y, H:i') }}
 *
 *   TextColumn::make('created_at')
 *       ->dateTime('d M Y H:i', timezone: \App\Support\MarketplaceTz::tz())
 *
 *   \App\Support\MarketplaceTz::fmt($order->created_at, 'd M Y H:i', $order->marketplaceClient)
 */
class MarketplaceTz
{
    /**
     * Hard fallback when no marketplace context can be resolved.
     * Matches the 25+ hardcoded 'Europe/Bucharest' references already in the codebase.
     */
    public const DEFAULT_TIMEZONE = 'Europe/Bucharest';

    /**
     * Resolve the active timezone string.
     *
     * @param  MarketplaceClient|null  $client  Explicit marketplace (e.g. $order->marketplaceClient)
     *                                          — bypasses auth-based resolution.
     */
    public static function tz(?MarketplaceClient $client = null): string
    {
        if ($client !== null) {
            return self::normalize($client->timezone);
        }

        return self::normalize(self::resolveCurrent()?->timezone);
    }

    /**
     * Format a Carbon/datetime in the marketplace timezone.
     * Returns '—' for null input so blade callers don't need null-guards.
     */
    public static function fmt(
        mixed $datetime,
        string $format = 'd M Y H:i',
        ?MarketplaceClient $client = null,
        string $fallback = '—',
    ): string {
        $carbon = self::toCarbon($datetime);

        if ($carbon === null) {
            return $fallback;
        }

        return $carbon->copy()->setTimezone(self::tz($client))->format($format);
    }

    /**
     * Best-effort resolution of the current marketplace from auth context.
     * Mirrors HasMarketplaceContext::getMarketplaceClient() but as a public helper.
     */
    public static function resolveCurrent(): ?MarketplaceClient
    {
        try {
            $admin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
            if ($admin && method_exists($admin, 'marketplaceClient')) {
                $client = $admin->marketplaceClient;
                if ($client) {
                    return $client;
                }
            }

            $user = auth()->user();
            if ($user && method_exists($user, 'marketplaceClient')) {
                $client = $user->marketplaceClient;
                if ($client) {
                    return $client;
                }
            }
        } catch (\Throwable $e) {
            // Auth not booted (console, early bootstrap) — fall through to default.
        }

        return null;
    }

    /**
     * Validate & sanitize a timezone string. Invalid values fall back to the default.
     */
    private static function normalize(?string $tz): string
    {
        if (! $tz) {
            return self::DEFAULT_TIMEZONE;
        }

        return in_array($tz, \DateTimeZone::listIdentifiers(), true)
            ? $tz
            : self::DEFAULT_TIMEZONE;
    }

    private static function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

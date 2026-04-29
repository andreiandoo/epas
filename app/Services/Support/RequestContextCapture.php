<?php

namespace App\Services\Support;

use Illuminate\Http\Request;

/**
 * Captures a snapshot of the request's environment for support tickets.
 *
 * Lightweight User-Agent parser inline — accurate enough to show staff
 * "Chrome 130 on Windows 10" in the context panel without pulling in a
 * full UA-parsing library.
 */
class RequestContextCapture
{
    public function capture(Request $request, array $clientHints = []): array
    {
        $userAgent = (string) ($request->userAgent() ?? '');
        $parsed = self::parseUserAgent($userAgent);

        $clientIp = $this->resolveClientIp($request);

        return array_filter([
            'ip' => $clientIp,
            'user_agent' => $userAgent ?: null,
            'browser' => $parsed['browser'] ?? null,
            'browser_version' => $parsed['browser_version'] ?? null,
            'os' => $parsed['os'] ?? null,
            'os_version' => $parsed['os_version'] ?? null,
            'device_type' => $parsed['device_type'] ?? null,
            'is_mobile' => $parsed['is_mobile'] ?? null,
            'language' => $request->getPreferredLanguage(['ro', 'en', 'de', 'fr', 'es']) ?: $request->header('Accept-Language'),
            'referer' => $request->header('Referer'),
            'source_url' => $clientHints['source_url'] ?? $request->header('X-Source-URL') ?? null,
            'screen_resolution' => $clientHints['screen_resolution'] ?? null,
            'viewport' => $clientHints['viewport'] ?? null,
            'captured_at' => now()->toIso8601String(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Parse a User-Agent string into browser/os/device. Order of checks
     * matters — Chrome's UA contains "Safari", Edge's contains "Chrome",
     * etc. — so we test the most specific marker first.
     */
    public static function parseUserAgent(string $ua): array
    {
        if ($ua === '') {
            return [];
        }

        $browser = null;
        $browserVersion = null;
        if (preg_match('#Edg/([\d.]+)#', $ua, $m)) {
            $browser = 'Edge';
            $browserVersion = $m[1];
        } elseif (preg_match('#OPR/([\d.]+)#', $ua, $m)) {
            $browser = 'Opera';
            $browserVersion = $m[1];
        } elseif (preg_match('#Firefox/([\d.]+)#', $ua, $m)) {
            $browser = 'Firefox';
            $browserVersion = $m[1];
        } elseif (preg_match('#Chrome/([\d.]+)#', $ua, $m)) {
            $browser = 'Chrome';
            $browserVersion = $m[1];
        } elseif (preg_match('#Version/([\d.]+).*Safari#', $ua, $m)) {
            $browser = 'Safari';
            $browserVersion = $m[1];
        } elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/')) {
            $browser = 'Internet Explorer';
            if (preg_match('#(?:MSIE |rv:)([\d.]+)#', $ua, $m)) {
                $browserVersion = $m[1];
            }
        }

        $os = null;
        $osVersion = null;
        if (preg_match('#Windows NT ([\d.]+)#', $ua, $m)) {
            $os = 'Windows';
            $osVersion = self::windowsVersion($m[1]);
        } elseif (preg_match('#Mac OS X ([\d_.]+)#', $ua, $m)) {
            $os = 'macOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (preg_match('#Android ([\d.]+)#', $ua, $m)) {
            $os = 'Android';
            $osVersion = $m[1];
        } elseif (preg_match('#iPhone OS ([\d_]+)#', $ua, $m) || preg_match('#iPad.*OS ([\d_]+)#', $ua, $m)) {
            $os = str_contains($ua, 'iPad') ? 'iPadOS' : 'iOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        $isMobile = (bool) preg_match('#Mobile|Android|iPhone|iPad|iPod#', $ua);
        $isTablet = (bool) preg_match('#iPad|Android(?!.*Mobile)#', $ua);
        $deviceType = $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop');

        return [
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'device_type' => $deviceType,
            'is_mobile' => $isMobile,
        ];
    }

    /**
     * Map "Windows NT X.Y" to a marketing name.
     */
    private static function windowsVersion(string $nt): string
    {
        return match ($nt) {
            '10.0' => '10/11',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            '6.0' => 'Vista',
            '5.1', '5.2' => 'XP',
            default => $nt,
        };
    }

    /**
     * Resolve the real client IP through trusted proxies. Falls back to
     * REMOTE_ADDR if no forwarded headers exist or trust isn't configured.
     */
    private function resolveClientIp(Request $request): ?string
    {
        // Laravel's Request::ip() honors trusted_proxies config when the
        // TrustProxies middleware is registered (which the codebase does).
        return $request->ip();
    }
}

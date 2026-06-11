<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceNewsletterLinkEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Newsletter open + click tracking endpoints.
 *
 * The HTML rendered by NewsletterRenderer is rewritten so that every
 * outbound link goes through {@see click()} and a 1x1 transparent
 * tracking pixel pointing at {@see open()} sits at the end of the
 * email body.
 *
 * Tokens encode (newsletter_id, dest_url, recipient_id?) + an HMAC
 * signature over APP_KEY, so the URLs are self-validating without DB
 * lookups for the token itself.
 *
 * Both endpoints are intentionally permissive (no auth, no CSRF, no
 * referer checks) — they sit at the public edge and must be reachable
 * from any inbox client and from forwarded copies of the email.
 */
class NewsletterTrackingController extends Controller
{
    /**
     * Click redirect. Logs a click row in
     * marketplace_newsletter_link_events, increments the aggregate
     * clicked_count on the newsletter, and 302's to the original dest
     * (with utm_source / utm_medium / utm_campaign appended so any
     * downstream analytics on ambilet.ro / etc. attribute correctly).
     */
    public function click(Request $request, string $token)
    {
        $payload = static::decodeToken($token);
        if (!$payload) abort(404);

        $newsletterId = (int) ($payload['n'] ?? 0);
        $dest = (string) ($payload['u'] ?? '');
        $recipientId = isset($payload['r']) ? (int) $payload['r'] : null;
        $linkKey = (string) ($payload['k'] ?? sha1($dest));

        if ($newsletterId <= 0 || $dest === '') abort(404);

        // Allow only http/https — never redirect to a custom scheme.
        if (!preg_match('#^https?://#i', $dest)) abort(404);

        try {
            DB::transaction(function () use ($newsletterId, $linkKey, $dest, $recipientId, $request) {
                MarketplaceNewsletterLinkEvent::create([
                    'newsletter_id' => $newsletterId,
                    'event_type' => MarketplaceNewsletterLinkEvent::TYPE_CLICK,
                    'link_key' => $linkKey,
                    'dest_url' => $dest,
                    'recipient_id' => $recipientId,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 512),
                    'referer' => substr((string) $request->headers->get('referer'), 0, 1024),
                ]);

                MarketplaceNewsletter::where('id', $newsletterId)->increment('clicked_count');
            });
        } catch (\Throwable $e) {
            Log::warning('Newsletter click logging failed', [
                'newsletter_id' => $newsletterId,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->away(static::withUtmParams($dest, $newsletterId));
    }

    /**
     * 1x1 transparent GIF tracking pixel. Records an open row, bumps
     * the newsletter's opened_count. Returns the pixel with cache-busting
     * headers so subsequent re-renders by the email client (some
     * pre-fetch images aggressively) still count.
     */
    public function open(Request $request, string $token)
    {
        $payload = static::decodeToken($token);
        if (!$payload) return static::pixel();

        $newsletterId = (int) ($payload['n'] ?? 0);
        $recipientId = isset($payload['r']) ? (int) $payload['r'] : null;

        if ($newsletterId > 0) {
            try {
                DB::transaction(function () use ($newsletterId, $recipientId, $request) {
                    MarketplaceNewsletterLinkEvent::create([
                        'newsletter_id' => $newsletterId,
                        'event_type' => MarketplaceNewsletterLinkEvent::TYPE_OPEN,
                        'recipient_id' => $recipientId,
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 512),
                    ]);

                    MarketplaceNewsletter::where('id', $newsletterId)->increment('opened_count');
                });
            } catch (\Throwable $e) {
                Log::warning('Newsletter open logging failed', [
                    'newsletter_id' => $newsletterId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return static::pixel();
    }

    /**
     * Build a token for use in the email's tracked URLs. Public so the
     * renderer can call it directly. Token format:
     *   base64url(JSON({n, u, r?, k?})) . '.' . base64url(HMAC-SHA256)
     */
    public static function buildToken(int $newsletterId, ?string $destUrl, ?int $recipientId = null): string
    {
        $payload = ['n' => $newsletterId];
        if ($destUrl !== null) {
            $payload['u'] = $destUrl;
            $payload['k'] = substr(sha1($destUrl), 0, 16);
        }
        if ($recipientId !== null) {
            $payload['r'] = $recipientId;
        }

        $body = static::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = static::base64UrlEncode(hash_hmac('sha256', $body, config('app.key'), true));

        return $body . '.' . $sig;
    }

    protected static function decodeToken(string $token): ?array
    {
        if (!str_contains($token, '.')) return null;
        [$body, $sig] = explode('.', $token, 2);
        $expected = static::base64UrlEncode(hash_hmac('sha256', $body, config('app.key'), true));
        if (!hash_equals($expected, $sig)) return null;

        $decoded = json_decode((string) static::base64UrlDecode($body), true);
        return is_array($decoded) ? $decoded : null;
    }

    protected static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $raw): string|false
    {
        $padded = strtr($raw, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) $padded .= str_repeat('=', 4 - $pad);
        return base64_decode($padded, true);
    }

    /**
     * Append UTM tracking parameters to the dest URL so downstream
     * analytics (GA, internal MarketplaceTracking, etc.) attribute the
     * inbound traffic to the newsletter campaign. Preserves existing
     * query string + fragment.
     */
    public static function withUtmParams(string $url, int $newsletterId): string
    {
        $parts = parse_url($url);
        if (!$parts) return $url;

        parse_str($parts['query'] ?? '', $query);
        $query['utm_source'] = $query['utm_source'] ?? 'newsletter';
        $query['utm_medium'] = $query['utm_medium'] ?? 'email';
        $query['utm_campaign'] = $query['utm_campaign'] ?? ('nl_' . $newsletterId);
        $query['nl'] = $newsletterId;

        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (!empty($parts['port'])) $rebuilt .= ':' . $parts['port'];
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?' . http_build_query($query);
        if (!empty($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];

        return $rebuilt;
    }

    protected static function pixel(): Response
    {
        // 1x1 transparent GIF, hex-encoded.
        $gif = hex2bin('47494638396101000100800000ffffff00000021f90401000000002c00000000010001000002024401003b');
        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}

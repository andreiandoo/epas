<?php

namespace App\Services\Partners;

use App\Models\MarketplacePartner;
use App\Models\MarketplacePartnerDelivery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends one delivery to a partner, signed with the partner's shared secret:
 *
 *   {Prefix}-Timestamp: unix seconds
 *   {Prefix}-Signature: hex(hmac_sha256(secret, timestamp + "." + body))
 *   {Prefix}-Delivery:  delivery id, for de-duplication on the partner side
 *
 * The prefix is X-Ambilet for event webhooks; ad requests use the partner's own
 * (settings.ads.header_prefix, e.g. X-MR). Records the outcome on the delivery.
 */
class PartnerRequestSender
{
    public const HEADER_PREFIX = 'X-Ambilet';

    private const TIMEOUT_SECONDS = 10;

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public static function headerPrefixFor(MarketplacePartner $partner, string $type): string
    {
        if (str_starts_with($type, 'ad.')) {
            $prefix = trim((string) Arr::get($partner->settings ?? [], 'ads.header_prefix'));
            if (preg_match('/^X-[A-Za-z0-9-]{1,30}$/', $prefix)) {
                return $prefix;
            }
        }

        return self::HEADER_PREFIX;
    }

    /**
     * Refuse anything but a public https endpoint, so an admin-entered URL cannot
     * reach the server's own network (localhost, private ranges, cloud metadata).
     *
     * @throws \RuntimeException
     */
    public static function assertPublicHttpsUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === '') {
            throw new \RuntimeException('The partner URL must use https.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (!$ips) {
            throw new \RuntimeException("Cannot resolve {$host}.");
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException("{$host} points to a private or reserved address.");
            }
        }
    }

    /**
     * @return bool whether the partner answered 2xx
     */
    public function send(MarketplacePartnerDelivery $delivery): bool
    {
        $partner = $delivery->partner;
        $secret = $partner?->outbound_secret;

        if (empty($secret)) {
            $this->fail($delivery, 'No signing secret is set for this partner.');

            return false;
        }

        try {
            static::assertPublicHttpsUrl($delivery->url);
        } catch (\RuntimeException $e) {
            $this->fail($delivery, $e->getMessage());

            return false;
        }

        $method = strtoupper($delivery->method ?: 'POST');
        $body = $method === 'DELETE'
            ? ''
            : json_encode($delivery->body ?? (object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $prefix = static::headerPrefixFor($partner, $delivery->type);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Tixello-Partner-API/1.0',
                "{$prefix}-Timestamp" => (string) $timestamp,
                "{$prefix}-Signature" => static::signature($secret, $timestamp, $body),
                "{$prefix}-Delivery" => (string) $delivery->id,
            ])
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withBody($body, 'application/json')
                ->send($method, $delivery->url);
        } catch (\Throwable $e) {
            $this->record($delivery, false, null, null, Str::limit($e->getMessage(), 500));

            return false;
        }

        $ok = $response->successful();
        $this->record(
            $delivery,
            $ok,
            $response->status(),
            Str::limit((string) $response->body(), 1000),
            $ok ? null : 'HTTP ' . $response->status()
        );

        return $ok;
    }

    /**
     * Worth trying again: no response, a server error, a timeout or rate limiting.
     */
    public static function isRetryable(?int $httpStatus): bool
    {
        return $httpStatus === null || $httpStatus >= 500 || in_array($httpStatus, [408, 425, 429], true);
    }

    /**
     * A problem no retry can fix: record it and mark the delivery failed.
     */
    private function fail(MarketplacePartnerDelivery $delivery, string $error): void
    {
        $this->record($delivery, false, null, null, $error);
        $delivery->forceFill(['status' => MarketplacePartnerDelivery::STATUS_FAILED])->save();
    }

    private function record(MarketplacePartnerDelivery $delivery, bool $ok, ?int $status, ?string $excerpt, ?string $error): void
    {
        $delivery->forceFill([
            'attempts' => $delivery->attempts + 1,
            'http_status' => $status,
            'response_excerpt' => $excerpt,
            'error' => $error,
            'status' => $ok ? MarketplacePartnerDelivery::STATUS_SENT : $delivery->status,
            'sent_at' => $ok ? now() : $delivery->sent_at,
        ])->save();
    }
}

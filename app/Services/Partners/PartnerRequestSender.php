<?php

namespace App\Services\Partners;

use App\Models\MarketplacePartnerDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends one delivery to a partner, signed with the partner's shared secret:
 *
 *   {Prefix}-Timestamp: unix seconds
 *   {Prefix}-Signature: hex(hmac_sha256(secret, timestamp + "." + body))
 *   {Prefix}-Delivery:  delivery id, for de-duplication on the partner side
 *
 * Records the outcome of the attempt on the delivery.
 */
class PartnerRequestSender
{
    public const HEADER_PREFIX = 'X-Ambilet';

    private const TIMEOUT_SECONDS = 10;

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * @return bool whether the partner answered 2xx
     */
    public function send(MarketplacePartnerDelivery $delivery): bool
    {
        $partner = $delivery->partner;
        $secret = $partner?->outbound_secret;

        if (empty($secret)) {
            $this->record($delivery, false, null, null, 'No signing secret is set for this partner.');
            // Nothing a retry could fix.
            $delivery->forceFill(['status' => MarketplacePartnerDelivery::STATUS_FAILED])->save();

            return false;
        }

        $method = strtoupper($delivery->method ?: 'POST');
        $body = $method === 'DELETE'
            ? ''
            : json_encode($delivery->body ?? (object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $prefix = self::HEADER_PREFIX;

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Tixello-Partner-API/1.0',
                "{$prefix}-Timestamp" => (string) $timestamp,
                "{$prefix}-Signature" => static::signature($secret, $timestamp, $body),
                "{$prefix}-Delivery" => (string) $delivery->id,
            ])
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

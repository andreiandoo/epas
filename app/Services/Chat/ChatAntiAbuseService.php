<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatBlocklistEntry;
use Illuminate\Support\Facades\Cache;

/**
 * Anti-bot / anti-spam guard for the public chat endpoints. Layered checks:
 *   - blocklist (IP / email),
 *   - honeypot field (hidden input; any value => bot),
 *   - time-trap (pre-chat submitted implausibly fast),
 *   - rate limiting (conversations per IP, messages per minute).
 *
 * Methods return a reason string when the request should be blocked, or null
 * when it passes. Callers translate a non-null reason into an HTTP 429/403.
 */
class ChatAntiAbuseService
{
    /**
     * Guard opening a new conversation. Returns a block reason or null.
     *
     * @param  array<string, mixed>  $payload  raw pre-chat payload (may contain honeypot + elapsed_seconds)
     */
    public function guardConversationOpen(int $marketplaceClientId, string $ip, ?string $email, array $payload): ?string
    {
        if ($this->isBlocked($marketplaceClientId, $ip, $email)) {
            return 'blocked';
        }

        // Honeypot: a hidden field that real users never fill.
        $honeypot = config('chat.anti_bot.honeypot_field', 'company_website');
        if (!empty($payload[$honeypot])) {
            return 'honeypot';
        }

        // Time-trap: bots submit instantly.
        $minSeconds = (int) config('chat.anti_bot.min_prechat_seconds', 2);
        $elapsed = (int) ($payload['elapsed_seconds'] ?? PHP_INT_MAX);
        if ($elapsed < $minSeconds) {
            return 'too_fast';
        }

        // Rate limit conversations per IP.
        $max = (int) config('chat.anti_bot.max_conversations_per_ip', 5);
        $window = (int) config('chat.anti_bot.window_minutes', 10) * 60;
        $key = "chat:antiabuse:conv:{$marketplaceClientId}:{$ip}";
        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return 'rate_limited';
        }
        Cache::put($key, $count + 1, $window);

        return null;
    }

    /**
     * Guard posting a message. Returns a block reason or null.
     */
    public function guardMessage(int $conversationId): ?string
    {
        $max = (int) config('chat.anti_bot.max_messages_per_minute', 20);
        $key = "chat:antiabuse:msg:{$conversationId}:" . now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return 'rate_limited';
        }
        Cache::put($key, $count + 1, 60);

        return null;
    }

    /**
     * Is this IP or email on the active blocklist?
     */
    public function isBlocked(int $marketplaceClientId, ?string $ip, ?string $email): bool
    {
        $q = ChatBlocklistEntry::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->active()
            ->where(function ($sub) use ($ip, $email) {
                if ($ip) {
                    $sub->orWhere(fn ($x) => $x->where('type', ChatBlocklistEntry::TYPE_IP)->where('value', $ip));
                }
                if ($email) {
                    $sub->orWhere(fn ($x) => $x->where('type', ChatBlocklistEntry::TYPE_EMAIL)->where('value', $email));
                }
            });

        // If neither ip nor email supplied, nothing to match.
        if (!$ip && !$email) {
            return false;
        }

        return $q->exists();
    }
}

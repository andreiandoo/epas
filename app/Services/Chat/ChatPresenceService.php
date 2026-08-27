<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatOperatorStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Operator presence tracking. Live state lives in the cache (Redis in prod)
 * keyed per operator with a short TTL, so a crashed browser naturally decays
 * to offline. The chat_operator_statuses table is the durable mirror used by
 * the admin UI and by ChatScheduleService when the cache is cold.
 *
 * F0: DB mirror + cache heartbeat. F3 will additionally broadcast presence on
 * the `presence-chat.operators` Reverb channel.
 */
class ChatPresenceService
{
    private function key(int $adminId): string
    {
        return "chat:presence:operator:{$adminId}";
    }

    private function ttl(): int
    {
        return (int) config('chat.operator.presence_ttl_seconds', 60);
    }

    /**
     * Operator heartbeat / status change. Writes the cache (with TTL) and the
     * durable mirror row.
     */
    public function heartbeat(int $marketplaceClientId, int $adminId, string $presence = ChatOperatorStatus::PRESENCE_ONLINE): void
    {
        Cache::put($this->key($adminId), $presence, $this->ttl());

        ChatOperatorStatus::updateOrCreate(
            ['marketplace_admin_id' => $adminId],
            [
                'marketplace_client_id' => $marketplaceClientId,
                'presence' => $presence,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * Explicitly mark an operator offline (logout / toggle off).
     */
    public function goOffline(int $adminId): void
    {
        Cache::forget($this->key($adminId));
        ChatOperatorStatus::query()
            ->where('marketplace_admin_id', $adminId)
            ->update(['presence' => ChatOperatorStatus::PRESENCE_OFFLINE, 'last_seen_at' => now()]);
    }

    /**
     * Live presence for an operator, honouring cache TTL decay.
     */
    public function presenceOf(int $adminId): string
    {
        $cached = Cache::get($this->key($adminId));
        if ($cached) {
            return $cached;
        }
        return ChatOperatorStatus::PRESENCE_OFFLINE;
    }

    public function isOnline(int $adminId): bool
    {
        return $this->presenceOf($adminId) === ChatOperatorStatus::PRESENCE_ONLINE;
    }

    /**
     * Adjust the active-chat counter used for capacity checks.
     */
    public function incrementActiveChats(int $adminId, int $delta = 1): void
    {
        $status = ChatOperatorStatus::query()->where('marketplace_admin_id', $adminId)->first();
        if (!$status) {
            return;
        }
        $status->active_chats_count = max(0, $status->active_chats_count + $delta);
        $status->save();
    }
}

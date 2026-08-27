<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatOperatorStatus;
use Illuminate\Console\Command;

/**
 * Marks operator status rows offline when their heartbeat has gone stale.
 * Live presence decays via the cache TTL; this keeps the durable DB mirror
 * (used for availability when the cache is cold) honest. Runs every minute.
 */
class ChatCleanupPresenceCommand extends Command
{
    protected $signature = 'chat:cleanup-presence';

    protected $description = 'Mark chat operators offline when their presence heartbeat is stale.';

    public function handle(): int
    {
        $ttl = (int) config('chat.operator.presence_ttl_seconds', 60);
        // Grace factor: only flip after 2x the heartbeat window to avoid flapping.
        $cutoff = now()->subSeconds($ttl * 2);

        $count = ChatOperatorStatus::withoutGlobalScopes()
            ->where('presence', '!=', ChatOperatorStatus::PRESENCE_OFFLINE)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff);
            })
            ->update(['presence' => ChatOperatorStatus::PRESENCE_OFFLINE]);

        $this->info("Marked {$count} stale operator(s) offline.");
        return self::SUCCESS;
    }
}

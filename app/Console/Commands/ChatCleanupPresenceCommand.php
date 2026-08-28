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
        // Only flip after a generous idle window so brief tab-switches (which
        // pause the console's wire:poll heartbeat) don't knock a working
        // operator offline. Explicit Offline/logout flips immediately elsewhere.
        $minutes = (int) config('chat.operator.offline_after_minutes', 30);
        $cutoff = now()->subMinutes($minutes);

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

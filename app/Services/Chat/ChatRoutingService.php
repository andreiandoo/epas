<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatOperatorStatus;
use Illuminate\Support\Facades\DB;

/**
 * Assigns queued conversations to available operators.
 *
 * Strategy is configurable ('least_busy' default, or 'round_robin'). Only
 * online operators with a free capacity slot are eligible. Assignment is done
 * inside a transaction with a row lock on the operator status to avoid two
 * conversations grabbing the last slot concurrently.
 *
 * F0: core picker + assignment. F3 will broadcast ConversationAssigned on the
 * operator's Reverb channel; for now the operator console polls the queue.
 */
class ChatRoutingService
{
    public function __construct(private ChatPresenceService $presence)
    {
    }

    /**
     * Pick the best eligible operator id for this conversation, or null if none
     * are available (caller should leave it queued).
     */
    public function pickOperator(ChatConversation $conversation): ?int
    {
        $strategy = config('chat.operator.assignment_strategy', 'least_busy');
        $default = (int) config('chat.operator.default_max_concurrent_chats', 4);

        $query = ChatOperatorStatus::query()
            ->where('marketplace_client_id', $conversation->marketplace_client_id)
            ->where('presence', ChatOperatorStatus::PRESENCE_ONLINE)
            ->whereRaw('active_chats_count < COALESCE(max_concurrent_chats, ?)', [$default]);

        if ($strategy === 'round_robin') {
            // Oldest last_seen_at that still has a free slot → spreads the load.
            $query->orderBy('active_chats_count')->orderBy('last_seen_at');
        } else { // least_busy
            $query->orderBy('active_chats_count')->orderByDesc('last_seen_at');
        }

        return $query->value('marketplace_admin_id');
    }

    /**
     * Atomically assign the conversation to an available operator. Returns the
     * assigned operator id, or null if the conversation stays queued.
     */
    public function assign(ChatConversation $conversation): ?int
    {
        return DB::transaction(function () use ($conversation) {
            $adminId = $this->pickOperator($conversation);
            if (!$adminId) {
                return null;
            }

            // Lock the operator row and re-check capacity under the lock.
            $default = (int) config('chat.operator.default_max_concurrent_chats', 4);
            $status = ChatOperatorStatus::query()
                ->where('marketplace_admin_id', $adminId)
                ->lockForUpdate()
                ->first();

            $cap = $status->max_concurrent_chats ?? $default;
            if (!$status || $status->presence !== ChatOperatorStatus::PRESENCE_ONLINE || $status->active_chats_count >= $cap) {
                return null;
            }

            $status->increment('active_chats_count');

            $conversation->forceFill([
                'assigned_to_marketplace_admin_id' => $adminId,
                'status' => ChatConversation::STATUS_ACTIVE,
                'assigned_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            return $adminId;
        });
    }

    /**
     * Release an operator slot when a conversation ends or is transferred away.
     */
    public function release(ChatConversation $conversation): void
    {
        if ($conversation->assigned_to_marketplace_admin_id) {
            $this->presence->incrementActiveChats($conversation->assigned_to_marketplace_admin_id, -1);
        }
    }
}

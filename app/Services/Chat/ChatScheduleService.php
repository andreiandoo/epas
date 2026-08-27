<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatHoliday;
use App\Models\Chat\ChatOperatorSchedule;
use App\Models\Chat\ChatOperatorStatus;
use Carbon\Carbon;

/**
 * Decides whether the chat is "open" (live operators can be reached) or
 * "offline" (leave-a-message flow), based on:
 *   1. marketplace holidays,
 *   2. at least one operator whose weekly schedule covers "now",
 *   3. at least one operator currently online with a free slot.
 *
 * Presence (3) is authoritative for live routing; schedule (2) drives the
 * widget's advertised availability even before an operator toggles online.
 */
class ChatScheduleService
{
    /**
     * Is today a marketplace holiday?
     */
    public function isHoliday(int $marketplaceClientId, ?Carbon $at = null): bool
    {
        $date = ($at ?: now())->toDateString();

        return ChatHoliday::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Is at least one operator scheduled to work at this moment?
     */
    public function hasScheduledOperator(int $marketplaceClientId, ?Carbon $at = null): bool
    {
        $now = $at ?: now();
        if ($this->isHoliday($marketplaceClientId, $now)) {
            return false;
        }

        $dow = (int) $now->dayOfWeek; // 0=Sun..6=Sat
        $time = $now->format('H:i:s');

        return ChatOperatorSchedule::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('is_active', true)
            ->where('day_of_week', $dow)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->exists();
    }

    /**
     * Is at least one operator online right now with capacity to take a chat?
     */
    public function hasAvailableOperator(int $marketplaceClientId): bool
    {
        return ChatOperatorStatus::query()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('presence', ChatOperatorStatus::PRESENCE_ONLINE)
            ->whereColumn('active_chats_count', '<', 'max_concurrent_chats')
            ->exists()
            // Also honour operators using the config default (NULL override):
            || ChatOperatorStatus::query()
                ->where('marketplace_client_id', $marketplaceClientId)
                ->where('presence', ChatOperatorStatus::PRESENCE_ONLINE)
                ->whereNull('max_concurrent_chats')
                ->where('active_chats_count', '<', (int) config('chat.operator.default_max_concurrent_chats', 4))
                ->exists();
    }

    /**
     * Widget availability state: 'online' (live), 'queue' (scheduled but nobody
     * free yet), or 'offline' (out of hours / holiday).
     */
    public function availabilityState(int $marketplaceClientId): string
    {
        if ($this->hasAvailableOperator($marketplaceClientId)) {
            return 'online';
        }
        if ($this->hasScheduledOperator($marketplaceClientId)) {
            return 'queue';
        }
        return 'offline';
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatConversation;
use App\Services\Chat\ChatConversationService;
use Illuminate\Console\Command;

/**
 * Auto-resolves conversations that have gone quiet past the inactivity timeout.
 * Frees the operator slot (via the service's resolve()) and posts a system line.
 * Runs every few minutes from the scheduler.
 */
class ChatCloseInactiveCommand extends Command
{
    protected $signature = 'chat:close-inactive {--minutes= : Override the inactivity timeout}';

    protected $description = 'Auto-resolve live-chat conversations inactive past the timeout.';

    public function handle(ChatConversationService $conversations): int
    {
        $activeMinutes = (int) ($this->option('minutes') ?: config('chat.conversation.inactivity_timeout_minutes', 4));
        $queueMinutes = (int) config('chat.conversation.queue_timeout_minutes', 60);
        $activeCutoff = now()->subMinutes($activeMinutes);
        $queueCutoff = now()->subMinutes($queueMinutes);

        // Active chats close quickly on inactivity; queued/offline chats wait much
        // longer so a visitor isn't dropped before an operator picks up.
        // withoutGlobalScopes so the CLI (no marketplace request context) sees all.
        $stale = ChatConversation::withoutGlobalScopes()
            ->where(function ($q) use ($activeCutoff, $queueCutoff) {
                $q->where(function ($a) use ($activeCutoff) {
                    $a->where('status', ChatConversation::STATUS_ACTIVE)
                        ->where('last_activity_at', '<', $activeCutoff);
                })->orWhere(function ($b) use ($queueCutoff) {
                    $b->whereIn('status', [ChatConversation::STATUS_QUEUED, ChatConversation::STATUS_OFFLINE_MESSAGE])
                        ->where('last_activity_at', '<', $queueCutoff);
                });
            })
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($stale as $conversation) {
            $conversations->postSystemMessage($conversation, 'Conversație închisă automat din inactivitate.');
            $conversations->resolve($conversation, ChatConversation::STATUS_CLOSED);
            $count++;
        }

        $this->info("Closed {$count} inactive conversation(s).");
        return self::SUCCESS;
    }
}

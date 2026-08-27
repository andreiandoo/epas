<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GDPR transcript retention: permanently deletes closed/resolved conversations
 * (and their messages) older than the retention window. Retention is per
 * marketplace (microservice setting) with a global config fallback.
 *
 *   php artisan chat:purge-transcripts            (uses configured retention)
 *   php artisan chat:purge-transcripts --days=90  (override)
 *   php artisan chat:purge-transcripts --dry-run
 */
class ChatPurgeTranscriptsCommand extends Command
{
    protected $signature = 'chat:purge-transcripts {--days= : Override retention days} {--dry-run : Report only, delete nothing}';

    protected $description = 'Purge old resolved/closed chat transcripts per retention policy (GDPR).';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('chat.conversation.transcript_retention_days', 365));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $query = ChatConversation::withoutGlobalScopes()
            ->whereIn('status', [ChatConversation::STATUS_RESOLVED, ChatConversation::STATUS_CLOSED])
            ->where(function ($q) use ($cutoff) {
                $q->where('closed_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('closed_at')->where('resolved_at', '<', $cutoff);
                    });
            });

        $ids = $query->limit(1000)->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Nothing to purge.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[dry-run] Would purge {$ids->count()} conversation(s) older than {$days} days.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            ChatMessage::withoutGlobalScopes()->whereIn('chat_conversation_id', $ids)->delete();
            ChatConversation::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        });

        $this->info("Purged {$ids->count()} conversation(s) older than {$days} days.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use Illuminate\Console\Command;

class CleanupOldChatConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:cleanup
                            {--days=30 : Delete resolved conversations older than X days}
                            {--all : Also delete open/escalated conversations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old chat conversations and their messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $includeAll = $this->option('all');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up chat conversations older than {$days} days...");
        $this->info("Cutoff date: {$cutoffDate->toDateTimeString()}");

        $query = ChatConversation::where('created_at', '<', $cutoffDate);

        if (!$includeAll) {
            $query->where('status', 'resolved');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No conversations to clean up.');
            return Command::SUCCESS;
        }

        // Delete in chunks to avoid memory issues (messages cascade on delete)
        $deleted = 0;
        $query->chunkById(100, function ($conversations) use (&$deleted) {
            foreach ($conversations as $conversation) {
                $conversation->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} conversation(s) with their messages.");

        return Command::SUCCESS;
    }
}

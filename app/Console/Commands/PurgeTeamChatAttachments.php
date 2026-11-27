<?php

namespace App\Console\Commands;

use App\Models\TeamChat\TeamMessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Chatul intern păstrează mesajele pentru totdeauna, dar fișierele atașate doar 30 de zile
 * (un an pentru cele marcate „Păstrează”). Rândul rămâne, ca mesajul să arate „fișier expirat”.
 */
class PurgeTeamChatAttachments extends Command
{
    protected $signature = 'team-chat:purge-attachments {--dry-run : Doar numără}';

    protected $description = 'Șterge fișierele expirate din chatul intern al echipei';

    public function handle(): int
    {
        if (! Schema::hasTable('team_message_attachments')) {
            return self::SUCCESS;
        }

        $query = TeamMessageAttachment::query()
            ->whereNull('purged_at')
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('keep', false)->where('created_at', '<', now()->subDays(TeamMessageAttachment::RETENTION_DAYS)))
                  ->orWhere(fn ($w) => $w->where('keep', true)->where('created_at', '<', now()->subDays(TeamMessageAttachment::KEEP_DAYS)));
            });

        $count = (clone $query)->count();
        $this->line('Fișiere expirate: ' . $count);
        if ($this->option('dry-run') || $count === 0) {
            return self::SUCCESS;
        }

        $freed = 0;
        $query->orderBy('id')->chunkById(200, function ($rows) use (&$freed) {
            foreach ($rows as $a) {
                try {
                    Storage::disk('local')->delete($a->path);
                } catch (\Throwable $e) {
                    report($e);
                }
                $freed += (int) $a->size;
                $a->forceFill(['purged_at' => now()])->save();
            }
        });

        $this->info('Eliberat: ' . round($freed / 1048576, 1) . ' MB');

        return self::SUCCESS;
    }
}

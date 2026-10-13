<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invite;
use App\Models\InviteBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Frees the disk space held by rendered invitation PDFs that nobody needs
 * any more.
 *
 * Every invitation PDF (storage/app/private/invitations/{batch}/{code}.pdf)
 * is a derived copy: both the admin page ("Regenerate") and the organizer
 * API (POST .../invitations/{batch}/generate) rebuild it from the invite
 * rows, the template and the event. Check-in reads the invite code from the
 * database, never the file. At ~1.7 MB per PDF the folder reached 18 GB.
 *
 * A batch folder is removed when:
 *   - past     the batch's event ended more than --days days ago
 *   - deleted  the batch is soft-deleted
 *   - orphan   no batch row exists for the folder
 *
 * Batches whose event has no known end date are always kept. After a folder
 * is removed the invites' `urls` are cleared, so the UIs report "no PDF,
 * regenerate" instead of offering a dead download link.
 *
 * Dry-run by default — nothing is deleted without --force.
 *
 *   php artisan invitations:prune-pdfs
 *   php artisan invitations:prune-pdfs --days=30 --force
 */
class PruneInvitationPdfsCommand extends Command
{
    protected $signature = 'invitations:prune-pdfs
        {--days=30 : Keep PDFs until this many days after the event ended}
        {--force : Actually delete (without it the command only reports)}';

    protected $description = 'Delete rendered invitation PDFs for ended events, deleted batches and orphan folders (dry-run unless --force).';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $force = (bool) $this->option('force');
        $cutoff = now()->subDays($days)->startOfDay();
        $disk = Storage::disk('local');

        $totals = [];

        foreach ($disk->directories('invitations') as $dir) {
            $batchId = basename($dir);
            if (!ctype_digit($batchId)) {
                continue;
            }

            $reason = $this->pruneReason((int) $batchId, $cutoff);
            $bytes = 0;
            foreach ($disk->allFiles($dir) as $file) {
                $bytes += $disk->size($file);
            }

            $totals[$reason]['batches'] = ($totals[$reason]['batches'] ?? 0) + 1;
            $totals[$reason]['bytes'] = ($totals[$reason]['bytes'] ?? 0) + $bytes;

            if ($reason === 'keep' || !$force) {
                continue;
            }

            $disk->deleteDirectory($dir);
            Invite::withTrashed()
                ->where('batch_id', $batchId)
                ->whereNotNull('urls')
                ->update(['urls' => null]);
        }

        $rows = [];
        $freed = 0;
        foreach ($totals as $reason => $t) {
            $rows[] = [$reason, $t['batches'], number_format($t['bytes'] / 1048576, 1) . ' MB'];
            if ($reason !== 'keep') {
                $freed += $t['bytes'];
            }
        }
        $this->table(['Category', 'Batches', 'Size'], $rows);

        $freedMb = number_format($freed / 1048576, 1);
        $this->info($force
            ? "Deleted {$freedMb} MB of invitation PDFs."
            : "Dry-run: {$freedMb} MB would be deleted. Re-run with --force to delete.");

        return self::SUCCESS;
    }

    protected function pruneReason(int $batchId, \DateTimeInterface $cutoff): string
    {
        $batch = InviteBatch::withTrashed()->find($batchId);

        if (!$batch) {
            return 'orphan';
        }
        if ($batch->trashed()) {
            return 'deleted';
        }
        if (!ctype_digit((string) $batch->event_ref)) {
            return 'keep';
        }

        $event = Event::query()
            ->select(['id', 'event_date', 'range_end_date'])
            ->find((int) $batch->event_ref);
        $endDate = $event?->range_end_date ?? $event?->event_date;

        return $endDate && $endDate->lt($cutoff) ? 'past' : 'keep';
    }
}

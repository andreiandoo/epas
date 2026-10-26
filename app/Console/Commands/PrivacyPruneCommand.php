<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Termenele de păstrare promise în politica de confidențialitate
 * (/politica-confidentialitate, secțiunea „Cât păstrăm datele”):
 *  - înscrierile începute la /inregistrare și netrimise: 12 luni de la ultima activitate;
 *  - jurnalul emailurilor trimise: 24 de luni.
 */
class PrivacyPruneCommand extends Command
{
    protected $signature = 'privacy:prune {--dry-run : Doar arată ce s-ar șterge}';

    protected $description = 'Șterge datele care au depășit termenele din politica de confidențialitate';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Înscrieri netrimise: tenantul și userul lui, dacă userul nu mai are alt tenant
        $stale = Tenant::where('approval_status', 'incomplete')
            ->where('updated_at', '<', now()->subMonths(12))
            ->get(['id', 'owner_id']);
        $this->info("Înscrieri netrimise mai vechi de 12 luni: {$stale->count()}");
        if (! $dry) {
            foreach ($stale as $tenant) {
                // O înscriere care nu se poate șterge (are deja alte date legate) nu oprește restul
                try {
                    DB::transaction(function () use ($tenant) {
                        $ownerId = $tenant->owner_id;
                        $tenant->delete();
                        if ($ownerId && ! Tenant::where('owner_id', $ownerId)->exists()) {
                            User::where('id', $ownerId)->where('role', 'tenant')->delete();
                        }
                    });
                } catch (\Throwable $e) {
                    $this->warn("Înscrierea {$tenant->id} nu s-a putut șterge: {$e->getMessage()}");
                }
            }
        }

        // Jurnalul emailurilor
        $emails = EmailLog::where('created_at', '<', now()->subMonths(24));
        $count = (clone $emails)->count();
        $this->info("Emailuri în jurnal mai vechi de 24 de luni: {$count}");
        if (! $dry && $count) {
            $emails->chunkById(1000, fn ($rows) => EmailLog::whereIn('id', $rows->pluck('id'))->delete());
        }

        return self::SUCCESS;
    }
}

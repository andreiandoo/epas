<?php

namespace App\Jobs;

use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceOrganizer;
use App\Services\Marketplace\LegacySettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Soldurile din trecut, pe un organizator, în fundal: „Analizează toate” și
 * „Rezolvă selectate” din Balanțe. Un job pe organizator, ca niciunul să nu
 * dureze prea mult. Rezolvarea reface analiza și sare peste organizatorii
 * care nu mai sunt curați; rezultatul ajunge în coloana „Trecut”.
 */
class LegacySettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $organizerId,
        public string $action = 'analyze',
        public ?int $adminId = null,
    ) {
    }

    public function handle(LegacySettlementService $service): void
    {
        $organizer = MarketplaceOrganizer::find($this->organizerId);
        if (! $organizer || ! LegacySettlementService::appliesTo((int) $organizer->marketplace_client_id)) {
            return;
        }

        try {
            if ($this->action === 'settle') {
                $service->settle($organizer, $this->adminId ? MarketplaceAdmin::find($this->adminId) : null);
            } else {
                $service->analyze($organizer);
            }
        } catch (\Throwable $e) {
            // Rezolvarea refuzată (nu mai e curat) e un rezultat normal: analiza
            // din settle() a actualizat deja verdictul.
            Log::info('LegacySettlementJob: ' . $this->action . ' organizer ' . $this->organizerId . ': ' . $e->getMessage());
        } finally {
            Cache::decrement('legacy_settle:queue:' . $organizer->marketplace_client_id);
        }
    }
}

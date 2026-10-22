<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTaxTemplate;
use App\Services\Marketplace\OrganizerFiscalDocuments;
use Illuminate\Console\Command;

/**
 * Finds events whose fiscal documents the organizer has to issue and tells
 * each organizer once per problem (see OrganizerFiscalDocuments::notifyIfNeeded):
 *   - cerere de vizare not generated, event within CERERE_NOTICE_DAYS;
 *   - cerere de vizare outdated (prices / stock / codes changed);
 *   - event ended, impozit + PV distrugere not generated.
 * Already-announced problems are skipped, so running it daily repeats nothing.
 */
class OrganizerFiscalDocumentsNotify extends Command
{
    protected $signature = 'organizer-documents:notify
        {--marketplace= : Only this marketplace client id}
        {--dry-run : List what would be sent without sending}';

    protected $description = 'Tell organizers once about fiscal documents they have to issue or regenerate';

    public function handle(OrganizerFiscalDocuments $documents): int
    {
        if (! OrganizerFiscalDocuments::noticesAvailable()) {
            $this->warn('event_document_notices table missing — run the migration first.');

            return self::SUCCESS;
        }

        $marketplaceIds = MarketplaceTaxTemplate::query()
            ->whereIn('type', OrganizerFiscalDocuments::TYPES)
            ->where('is_active', true)
            ->when($this->option('marketplace'), fn ($q, $id) => $q->where('marketplace_client_id', (int) $id))
            ->distinct()
            ->pluck('marketplace_client_id');

        $dry = (bool) $this->option('dry-run');
        $sent = 0;
        foreach ($marketplaceIds as $marketplaceId) {
            foreach ($documents->candidateEvents((int) $marketplaceId) as $event) {
                $organizer = $event->marketplaceOrganizer;
                if (! $organizer || $organizer->marketplace_manages_documents || $organizer->organizer_type === 'leisure') {
                    continue;
                }
                if ($dry) {
                    if ($issue = $documents->issue($event)) {
                        $this->line("event {$event->id} (organizer {$organizer->id}): {$issue['kind']}");
                    }

                    continue;
                }
                if ($kind = $documents->notifyIfNeeded($event)) {
                    $sent++;
                    $this->line("event {$event->id}: {$kind}");
                }
            }
        }

        $this->info($dry ? 'Dry run done.' : "Notices sent: {$sent}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\InviteBatch;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Console\Command;

/**
 * Backfill Ticket rows for invitations that were generated before the
 * Organizer\InvitationsController started creating them automatically.
 *
 * Idempotent: skips invites whose invite_code already has a Ticket row,
 * so it's safe to run repeatedly. Scope it with --batch= or --event= when
 * you only want to touch a subset.
 */
class BackfillInvitationTickets extends Command
{
    protected $signature = 'invitations:backfill-tickets
        {--batch= : Only process this InviteBatch id}
        {--event= : Only process batches whose event_ref matches this Event id}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Create missing Ticket rows for existing invitations so they show in analytics/sales';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = InviteBatch::query();

        if ($batchId = $this->option('batch')) {
            $query->where('id', $batchId);
        }
        if ($eventId = $this->option('event')) {
            $query->where('event_ref', (string) $eventId);
        }

        $batches = $query->get();
        if ($batches->isEmpty()) {
            $this->warn('No batches match the given filters.');
            return self::SUCCESS;
        }

        $createdTicketTypes = 0;
        $createdTickets = 0;
        $skipped = 0;

        foreach ($batches as $batch) {
            $eventId = is_numeric($batch->event_ref) ? (int) $batch->event_ref : null;
            if (!$eventId) {
                $this->warn("Batch #{$batch->id}: event_ref '{$batch->event_ref}' is not numeric, skipping.");
                continue;
            }

            $event = Event::find($eventId);
            if (!$event) {
                $this->warn("Batch #{$batch->id}: Event {$eventId} no longer exists, skipping.");
                continue;
            }

            // Find-or-create the "Invitatie" ticket type for this event
            $ticketType = TicketType::where('event_id', $event->id)
                ->where('name', 'Invitatie')
                ->first();

            if (!$ticketType) {
                if ($dryRun) {
                    $this->line("  [dry-run] would create TicketType 'Invitatie' for event {$event->id}");
                } else {
                    $ticketType = TicketType::create([
                        'event_id' => $event->id,
                        'name' => 'Invitatie',
                        'price_cents' => 0,
                        'currency' => 'RON',
                        'quota_total' => -1,
                        'quota_sold' => 0,
                        'status' => 'active',
                        'meta' => ['is_invitation' => true],
                    ]);
                }
                $createdTicketTypes++;
            }

            $invites = $batch->invites()->get();
            $this->line("Batch #{$batch->id} ({$batch->name}) — event {$event->id}, {$invites->count()} invites");

            foreach ($invites as $invite) {
                if (Ticket::where('code', $invite->invite_code)->exists()) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] would create Ticket for invite {$invite->invite_code} ({$invite->getRecipientName()})");
                    $createdTickets++;
                    continue;
                }

                if (!$ticketType) {
                    $this->warn("  cannot create Ticket without a TicketType (batch #{$batch->id})");
                    continue;
                }

                Ticket::create([
                    'order_id' => null,
                    'ticket_type_id' => $ticketType->id,
                    'performance_id' => null,
                    'code' => $invite->invite_code,
                    'status' => 'valid',
                    'seat_label' => $invite->seat_ref,
                    'meta' => [
                        'is_invitation' => true,
                        'invite_batch_id' => $batch->id,
                        'beneficiary' => [
                            'name' => $invite->getRecipientName(),
                            'email' => $invite->getRecipientEmail(),
                            'phone' => $invite->getRecipientPhone(),
                            'company' => $invite->getRecipientCompany(),
                        ],
                    ],
                ]);
                $createdTickets++;
            }
        }

        $verb = $dryRun ? 'would create' : 'created';
        $this->info("Done. TicketTypes {$verb}: {$createdTicketTypes}. Tickets {$verb}: {$createdTickets}. Skipped (already had ticket): {$skipped}.");

        return self::SUCCESS;
    }
}

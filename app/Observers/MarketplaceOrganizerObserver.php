<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Services\MarketplaceNotificationService;
use App\Services\OrganizerContractService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarketplaceOrganizerObserver
{
    public function __construct(
        protected MarketplaceNotificationService $notificationService,
        protected OrganizerContractService $contractService,
    ) {}

    /**
     * Handle the MarketplaceOrganizer "created" event.
     */
    public function created(MarketplaceOrganizer $organizer): void
    {
        // Notify about new organizer registration
        if ($organizer->marketplace_client_id) {
            try {
                $this->notificationService->notifyOrganizerRegistration(
                    $organizer->marketplace_client_id,
                    $organizer->name ?? $organizer->email,
                    $organizer->company_name,
                    $organizer,
                    route('filament.marketplace.resources.organizers.edit', ['record' => $organizer->id])
                );
            } catch (\Exception $e) {
                Log::warning('Failed to create organizer registration notification', [
                    'organizer_id' => $organizer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the MarketplaceOrganizer "updated" event.
     */
    public function updated(MarketplaceOrganizer $organizer): void
    {
        // Check if verified_at changed from null to a value
        if ($organizer->isDirty('verified_at') && $organizer->verified_at !== null && $organizer->getOriginal('verified_at') === null) {
            // Idempotent: no-op if the contract was already generated at
            // onboarding (see AuthController::register).
            $this->contractService->generate($organizer);
        }

        // When "Bilete Test POS" is switched ON, backfill the Test POS ticket
        // type onto the organizer's existing upcoming non-leisure events.
        // EventObserver only auto-provisions on event CREATE, so events that
        // already existed when the flag was flipped would otherwise never get
        // the test ticket (the exact gap that made it "not work" for admins who
        // enable the option after the events were created).
        if ($organizer->wasChanged('test_pos_enabled') && $organizer->test_pos_enabled) {
            $this->provisionTestPosTickets($organizer);
        }
    }

    /**
     * Create the Test POS ticket type on the organizer's upcoming non-leisure
     * events. Idempotent (ensureTestTicketType() no-ops when it already exists
     * or the gate doesn't pass) and fail-safe (never derails the organizer save).
     */
    protected function provisionTestPosTickets(MarketplaceOrganizer $organizer): void
    {
        try {
            $today = Carbon::now('Europe/Bucharest')->startOfDay()->toDateString();

            Event::query()
                ->where('marketplace_organizer_id', $organizer->id)
                ->where(function ($q) {
                    $q->where('display_template', '!=', 'leisure_venue')
                        ->orWhereNull('display_template');
                })
                ->where(function ($q) use ($today) {
                    // Upcoming / undated only — a smoke test on a finished event
                    // is pointless and we don't want to litter historical events.
                    $q->whereNull('event_date')
                        ->orWhere('event_date', '>=', $today)
                        ->orWhere('range_end_date', '>=', $today);
                })
                ->get()
                ->each(fn (Event $event) => $event->ensureTestTicketType());
        } catch (\Throwable $e) {
            Log::warning('Failed to provision Test POS tickets on organizer toggle', [
                'organizer_id' => $organizer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Observers;

use App\Models\MarketplaceOrganizer;
use App\Services\MarketplaceNotificationService;
use App\Services\OrganizerContractService;
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
    }

}

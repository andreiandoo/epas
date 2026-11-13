<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\MarketplaceNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceEventObserver
{
    public function __construct(
        protected MarketplaceNotificationService $notificationService
    ) {}

    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        // Only for marketplace events
        if (!$event->marketplace_client_id || !$event->marketplace_organizer_id) {
            return;
        }

        try {
            $organizer = $event->marketplaceOrganizer;
            $organizerName = $organizer?->company_name ?? $organizer?->name ?? 'Organizator necunoscut';

            $eventTitle = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: $event->getTranslation('title')
                ?: 'Eveniment nou';

            $this->notificationService->notifyEventCreated(
                $event->marketplace_client_id,
                $eventTitle,
                $organizerName,
                $event,
                route('filament.marketplace.resources.events.edit', ['record' => $event->id])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create event notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        if ($event->wasChanged('marketplace_organizer_id')) {
            $this->propagateOrganizerChange($event);
        }

        // Only for marketplace events
        if (!$event->marketplace_client_id || !$event->marketplace_organizer_id) {
            return;
        }

        // Only notify for significant changes (skip minor updates)
        $significantFields = [
            'title', 'event_date', 'start_time', 'venue_id',
            'is_cancelled', 'is_postponed', 'is_published',
        ];

        $hasSignificantChange = false;
        foreach ($significantFields as $field) {
            if ($event->isDirty($field)) {
                $hasSignificantChange = true;
                break;
            }
        }

        if (!$hasSignificantChange) {
            return;
        }

        try {
            $organizer = $event->marketplaceOrganizer;
            $organizerName = $organizer?->company_name ?? $organizer?->name ?? 'Organizator necunoscut';

            $eventTitle = $event->getTranslation('title', 'ro')
                ?: $event->getTranslation('title', 'en')
                ?: $event->getTranslation('title')
                ?: 'Eveniment';

            $this->notificationService->notifyEventUpdated(
                $event->marketplace_client_id,
                $eventTitle,
                $organizerName,
                $event,
                route('filament.marketplace.resources.events.edit', ['record' => $event->id])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create event update notification', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Propagate a change of marketplace_organizer_id from the event to all
     * denormalized copies on related tables (currently only `orders`).
     *
     * Without this, the organizer dashboard, payouts, refunds etc. keep
     * pointing to the previous organizer because they filter by
     * `orders.marketplace_organizer_id`, not by `events.marketplace_organizer_id`.
     */
    protected function propagateOrganizerChange(Event $event): void
    {
        $newOrganizerId = $event->marketplace_organizer_id;
        $oldOrganizerId = $event->getOriginal('marketplace_organizer_id');

        try {
            $payoutsMoved = [];
            $ordersUpdated = DB::transaction(function () use ($event, $newOrganizerId, $oldOrganizerId, &$payoutsMoved) {
                $orders = DB::table('orders')
                    ->where('event_id', $event->id)
                    ->where(function ($q) use ($newOrganizerId) {
                        $q->where('marketplace_organizer_id', '!=', $newOrganizerId)
                            ->orWhereNull('marketplace_organizer_id');
                    })
                    ->update(['marketplace_organizer_id' => $newOrganizerId]);

                $payoutsMoved = $this->movePayouts($event, $oldOrganizerId, $newOrganizerId);

                return $orders;
            });

            Log::info('Event organizer changed — propagated to related records', [
                'event_id' => $event->id,
                'old_organizer_id' => $oldOrganizerId,
                'new_organizer_id' => $newOrganizerId,
                'orders_updated' => $ordersUpdated,
                'payouts_moved' => $payoutsMoved,
            ]);

            // Soldurile ambilor organizatori se schimbă (vânzări + deconturi).
            \App\Jobs\RecomputeOrganizerBalanceJob::schedule($oldOrganizerId ? (int) $oldOrganizerId : null);
            \App\Jobs\RecomputeOrganizerBalanceJob::schedule($newOrganizerId ? (int) $newOrganizerId : null);
        } catch (\Throwable $e) {
            Log::error('Failed to propagate event organizer change', [
                'event_id' => $event->id,
                'old_organizer_id' => $oldOrganizerId,
                'new_organizer_id' => $newOrganizerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Deconturile evenimentului merg cu el la noul organizator, altfel plata
     * rămâne scăzută din soldul celui vechi, iar vânzările se numără la cel nou
     * (HARD EVENTS → CERTUS: −50.829 / +60.174). Deconturile pe mai multe
     * evenimente (event_id NULL) nu se ating.
     *
     * @return array<int, int> id-urile deconturilor mutate
     */
    protected function movePayouts(Event $event, $oldOrganizerId, $newOrganizerId): array
    {
        if (! $oldOrganizerId || ! $newOrganizerId || (int) $oldOrganizerId === (int) $newOrganizerId) {
            return [];
        }

        $payouts = DB::table('marketplace_payouts')
            ->where('event_id', $event->id)
            ->where('marketplace_organizer_id', $oldOrganizerId)
            ->get(['id', 'status', 'amount', 'deleted_at']);

        if ($payouts->isEmpty()) {
            return [];
        }

        $ids = $payouts->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::table('marketplace_payouts')->whereIn('id', $ids)
            ->update(['marketplace_organizer_id' => $newOrganizerId]);

        DB::table('marketplace_transactions')->whereIn('marketplace_payout_id', $ids)
            ->where('marketplace_organizer_id', $oldOrganizerId)
            ->update(['marketplace_organizer_id' => $newOrganizerId]);

        // total_paid_out e un total ținut incremental: mutăm partea plătită.
        $paid = round((float) $payouts->where('status', 'completed')->whereNull('deleted_at')->sum('amount'), 2);
        if ($paid > 0) {
            DB::table('marketplace_organizers')->where('id', $oldOrganizerId)->decrement('total_paid_out', $paid);
            DB::table('marketplace_organizers')->where('id', $newOrganizerId)->increment('total_paid_out', $paid);
        }

        activity('payout-reassign')
            ->performedOn($event)
            ->causedBy(auth()->user())
            ->withProperties([
                'payout_ids' => $ids,
                'from_organizer' => (int) $oldOrganizerId,
                'to_organizer' => (int) $newOrganizerId,
                'paid_moved' => $paid,
            ])
            ->log('Deconturi mutate odată cu evenimentul');

        return $ids;
    }
}

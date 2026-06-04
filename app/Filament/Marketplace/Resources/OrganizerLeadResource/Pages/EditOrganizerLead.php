<?php

namespace App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\Marketplace\OrganizerLead;
use App\Models\Marketplace\OrganizerLeadEvent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganizerLead extends EditRecord
{
    protected static string $resource = OrganizerLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * If the status changed on save, log it. The form-driven path is the
     * one-click table action's slow cousin — both end up in the same
     * audit trail so dashboards can read both consistently.
     */
    protected function afterSave(): void
    {
        /** @var OrganizerLead $lead */
        $lead = $this->record;

        $originalStatus = $this->record->getOriginal('status');
        if ($originalStatus && $originalStatus !== $lead->status) {
            OrganizerLeadEvent::create([
                'lead_id'               => $lead->id,
                'marketplace_client_id' => $lead->marketplace_client_id,
                'event_type'            => OrganizerLeadEvent::TYPE_STATUS_CHANGED,
                'summary'               => "Status: {$originalStatus} → {$lead->status}",
                'payload'               => ['from' => $originalStatus, 'to' => $lead->status],
                'performed_by_user_id'  => auth()->id(),
            ]);
        }
    }
}

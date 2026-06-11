<?php

namespace App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\Marketplace\OrganizerLeadEvent;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizerLead extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = OrganizerLeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Marketplace scope + canonical "added by admin" stamping.
        $client = static::getMarketplaceClient();
        if ($client) {
            $data['marketplace_client_id'] = $client->id;
        }
        $data['source']       = $data['source']       ?? 'manual';
        $data['status']       = $data['status']       ?? 'new';
        $data['submitted_at'] = $data['submitted_at'] ?? now();
        return $data;
    }

    protected function afterCreate(): void
    {
        // Log the manual create as a timeline event so the audit log is
        // never empty even on hand-added leads.
        $lead = $this->record;
        OrganizerLeadEvent::create([
            'lead_id'               => $lead->id,
            'marketplace_client_id' => $lead->marketplace_client_id,
            'event_type'            => OrganizerLeadEvent::TYPE_NOTE,
            'summary'               => 'Lead adăugat manual din admin',
            'performed_by_user_id'  => auth()->id(),
        ]);
    }
}

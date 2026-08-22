<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected array $extracted = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Șterge')
                ->modalDescription('Aceasta șterge tenant-ul + userul de login. Locațiile linked rămân în DB (tenant_id se resetează).')
                ->before(function () {
                    /** @var Tenant $tenant */
                    $tenant = $this->record;
                    DB::transaction(function () use ($tenant) {
                        // Detach venues so the marketplace can re-link them
                        // to another tenant later. Venue rows survive.
                        Venue::where('tenant_id', $tenant->id)->update(['tenant_id' => null]);
                        // Owner user goes with the tenant — its whole point
                        // was to log in to this venue-owner account.
                        if ($tenant->owner_id) {
                            User::where('id', $tenant->owner_id)->delete();
                        }
                    });
                }),
        ];
    }

    /**
     * Extract the non-tenant fields before Filament's mass-update runs.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extracted = [
            'owner_first_name' => trim((string) ($data['owner_first_name'] ?? '')),
            'owner_last_name' => trim((string) ($data['owner_last_name'] ?? '')),
            'owner_password' => (string) ($data['owner_password'] ?? ''),
            'linked_venue_ids' => is_array($data['linked_venue_ids'] ?? null)
                ? array_values(array_filter(array_map('intval', $data['linked_venue_ids'])))
                : [],
        ];

        unset(
            $data['owner_first_name'],
            $data['owner_last_name'],
            $data['owner_email'],
            $data['owner_password'],
            $data['linked_venue_ids'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;
        $mcId = $tenant->created_by_marketplace_client_id;

        DB::transaction(function () use ($tenant, $mcId) {
            // Owner-user updates — name + optional password reset. Email is
            // read-only on the edit form so we don't accidentally break a
            // running Android session for the tenant.
            if ($tenant->owner_id) {
                $owner = User::find($tenant->owner_id);
                if ($owner) {
                    $dirty = [];
                    if ($this->extracted['owner_first_name'] !== '' && $this->extracted['owner_first_name'] !== (string) $owner->first_name) {
                        $dirty['first_name'] = $this->extracted['owner_first_name'];
                    }
                    if ($this->extracted['owner_last_name'] !== '' && $this->extracted['owner_last_name'] !== (string) $owner->last_name) {
                        $dirty['last_name'] = $this->extracted['owner_last_name'];
                    }
                    if (!empty($dirty)) {
                        $dirty['name'] = trim(($dirty['first_name'] ?? $owner->first_name) . ' ' . ($dirty['last_name'] ?? $owner->last_name))
                            ?: $owner->name;
                    }
                    if ($this->extracted['owner_password'] !== '') {
                        $dirty['password'] = Hash::make($this->extracted['owner_password']);
                    }
                    if (!empty($dirty)) {
                        $owner->update($dirty);
                    }
                }
            }

            // Venue re-sync — same marketplace-only guard as on create.
            // Two-step: detach venues that were unchecked, attach new ones.
            $newIds = $this->extracted['linked_venue_ids'];
            $currentIds = Venue::where('tenant_id', $tenant->id)
                ->where('marketplace_client_id', $mcId)
                ->pluck('id')
                ->all();

            $toDetach = array_diff($currentIds, $newIds);
            $toAttach = array_diff($newIds, $currentIds);

            if (!empty($toDetach)) {
                Venue::whereIn('id', $toDetach)->update(['tenant_id' => null]);
            }
            if (!empty($toAttach)) {
                Venue::query()
                    ->where('marketplace_client_id', $mcId)
                    ->whereIn('id', $toAttach)
                    ->update(['tenant_id' => $tenant->id]);
            }
        });

        Notification::make()
            ->title('Venue owner actualizat')
            ->success()
            ->send();
    }
}

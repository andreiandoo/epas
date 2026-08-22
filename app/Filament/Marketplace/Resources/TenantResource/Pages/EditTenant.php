<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

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
                        Venue::where('tenant_id', $tenant->id)->update(['tenant_id' => null]);
                        if ($tenant->owner_id) {
                            User::where('id', $tenant->owner_id)->delete();
                        }
                    });
                }),
        ];
    }

    /**
     * Same reasoning as CreateTenant::handleRecordCreation — the two
     * inputs (owner_* fields + linked_venue_ids) live on the form only
     * (dehydrated:false), so we intercept the Filament save hook that
     * still has $data and do the full owner+venue sync inside one
     * transaction. Splitting via mutate/afterSave + class prop hits the
     * Livewire lifecycle bug that blanked out the initial create.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Tenant $tenant */
        $tenant = $record;

        $firstName = trim((string) ($data['owner_first_name'] ?? ''));
        $lastName = trim((string) ($data['owner_last_name'] ?? ''));
        $password = (string) ($data['owner_password'] ?? '');
        $venueIds = is_array($data['linked_venue_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['linked_venue_ids'])))
            : [];

        // Strip non-tenant keys before the parent update — Tenant model
        // has no owner_* fillable columns and Filament's fillable-guard
        // would ignore them anyway, but explicit removal keeps the DB
        // insert payload predictable.
        $updateData = $data;
        unset(
            $updateData['owner_first_name'],
            $updateData['owner_last_name'],
            $updateData['owner_email'],
            $updateData['owner_password'],
            $updateData['linked_venue_ids'],
        );

        DB::transaction(function () use ($tenant, $updateData, $firstName, $lastName, $password, $venueIds) {
            // 1) Tenant fields
            $tenant->fill($updateData)->save();

            // 2) Owner user — name (concat) + optional password reset.
            //    Email stays read-only on the form (avoid breaking a
            //    live Android session for the tenant).
            if ($tenant->owner_id) {
                $owner = User::find($tenant->owner_id);
                if ($owner) {
                    $dirty = [];
                    $newFullName = trim($firstName . ' ' . $lastName);
                    if ($newFullName !== '' && $newFullName !== (string) $owner->name) {
                        $dirty['name'] = $newFullName;
                    }
                    if ($password !== '') {
                        $dirty['password'] = Hash::make($password);
                    }
                    if (!empty($dirty)) {
                        $owner->update($dirty);
                    }
                }
            }

            // 3) Venue re-sync — full DB catalog per operator spec
            //    (2026-08-22). Diff current↔new and apply as
            //    detach + attach.
            $currentIds = Venue::where('tenant_id', $tenant->id)
                ->pluck('id')
                ->all();
            $toDetach = array_diff($currentIds, $venueIds);
            $toAttach = array_diff($venueIds, $currentIds);

            if (!empty($toDetach)) {
                Venue::whereIn('id', $toDetach)->update(['tenant_id' => null]);
            }
            if (!empty($toAttach)) {
                Venue::whereIn('id', $toAttach)->update(['tenant_id' => $tenant->id]);
            }
        });

        return $tenant->refresh();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Venue owner actualizat')
            ->success();
    }
}

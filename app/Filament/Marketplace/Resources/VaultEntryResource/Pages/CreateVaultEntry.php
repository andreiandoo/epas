<?php

namespace App\Filament\Marketplace\Resources\VaultEntryResource\Pages;

use App\Filament\Marketplace\Resources\VaultEntryResource;
use App\Models\MarketplaceVaultEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVaultEntry extends CreateRecord
{
    protected static string $resource = VaultEntryResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Adaugă în seif';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $admin = VaultEntryResource::vaultAdmin();
        abort_unless($admin, 403);

        $accessIds = VaultEntryResource::sanitizeAccessIds((array) ($data['access_admin_ids'] ?? []));
        if ($accessIds === []) {
            throw ValidationException::withMessages([
                'data.access_admin_ids' => 'Selectează cel puțin un super-administrator.',
            ]);
        }

        return DB::transaction(function () use ($data, $admin, $accessIds) {
            $record = MarketplaceVaultEntry::create(array_merge(
                Arr::only($data, VaultEntryResource::ENTRY_FIELDS),
                [
                    'marketplace_client_id' => $admin->marketplace_client_id,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            ));

            $record->accessAdmins()->sync($accessIds);

            return $record;
        });
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Intrarea a fost adăugată în seif';
    }

    protected function getRedirectUrl(): string
    {
        // The creator may have left themselves off the access list.
        return VaultEntryResource::canView($this->getRecord())
            ? VaultEntryResource::getUrl('view', ['record' => $this->getRecord()])
            : VaultEntryResource::getUrl('index');
    }
}

<?php

namespace App\Filament\Marketplace\Resources\VaultEntryResource\Pages;

use App\Filament\Marketplace\Resources\VaultEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditVaultEntry extends EditRecord
{
    protected static string $resource = VaultEntryResource::class;

    public function getTitle(): string
    {
        return 'Editează: ' . $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['access_admin_ids'] = $this->getRecord()->accessAdmins()
            ->pluck('marketplace_admins.id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $admin = VaultEntryResource::vaultAdmin();
        abort_unless($admin && VaultEntryResource::canEdit($record), 403);

        $accessIds = VaultEntryResource::sanitizeAccessIds((array) ($data['access_admin_ids'] ?? []));
        if ($accessIds === []) {
            throw ValidationException::withMessages([
                'data.access_admin_ids' => 'Selectează cel puțin un super-administrator.',
            ]);
        }

        DB::transaction(function () use ($record, $data, $admin, $accessIds) {
            $record->update(array_merge(
                Arr::only($data, VaultEntryResource::ENTRY_FIELDS),
                ['updated_by' => $admin->id]
            ));

            $record->accessAdmins()->sync($accessIds);
        });

        return $record->load('accessAdmins');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Intrarea a fost salvată';
    }

    protected function getRedirectUrl(): ?string
    {
        // Admins who removed themselves from the access list go back to the list.
        return VaultEntryResource::canView($this->getRecord())
            ? VaultEntryResource::getUrl('view', ['record' => $this->getRecord()])
            : VaultEntryResource::getUrl('index');
    }
}

<?php

namespace App\Filament\Tenant\Resources\TenantTeamMemberResource\Pages;

use App\Filament\Tenant\Resources\TenantTeamMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantTeamMember extends EditRecord
{
    protected static string $resource = TenantTeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sync user.name update back to the linked User record.
        $userData = $this->data['user'] ?? [];
        $newName = $userData['name'] ?? null;
        if ($newName && $this->record->user && $this->record->user->name !== $newName) {
            $this->record->user->update(['name' => $newName]);
        }
        return $data;
    }
}

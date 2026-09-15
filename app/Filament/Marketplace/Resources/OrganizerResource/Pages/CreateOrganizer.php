<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Services\Marketplace\AccountPasswordSync;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrganizer extends CreateRecord
{
    protected static string $resource = OrganizerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $data['marketplace_client_id'] = $marketplaceAdmin->marketplace_client_id;
        $data['verified_at'] = $data['verified_at'] ?? now();
        $this->passwordForLinkedAccounts = filled($data['password'] ?? null) ? $data['password'] : null;

        return $data;
    }

    protected ?string $passwordForLinkedAccounts = null;

    protected function afterCreate(): void
    {
        if (!$this->passwordForLinkedAccounts) {
            return;
        }
        $password = $this->passwordForLinkedAccounts;
        $this->passwordForLinkedAccounts = null;

        $updated = rescue(fn () => app(AccountPasswordSync::class)
            ->applyToAll($this->record->marketplace_client_id, $this->record->email, $password, $this->record), []);
        if ($updated) {
            Notification::make()
                ->title('Parola a fost aplicată și celorlalte conturi')
                ->body(AccountPasswordSync::appliedNotice($updated))
                ->success()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}

<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingsResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class ManageSettings extends EditRecord
{
    protected static string $resource = SettingsResource::class;

    public function mount(int|string $record = null): void
    {
        // Always edit the first (and only) settings record
        $this->record = Setting::current();

        $this->authorizeAccess();

        $this->fillForm();

        $this->previousUrl = url()->previous();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reset_invoice_number')
                ->label('Reset Invoice Number')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['invoice_next_number' => 1]);
                    $this->notify('success', 'Invoice number reset to 1');
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Settings saved successfully';
    }

    public function getTitle(): string
    {
        return 'Application Settings';
    }
}

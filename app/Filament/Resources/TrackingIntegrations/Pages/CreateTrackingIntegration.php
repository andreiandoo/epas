<?php

namespace App\Filament\Resources\TrackingIntegrations\Pages;

use App\Filament\Resources\TrackingIntegrations\TrackingIntegrationResource;
use App\Services\Tracking\Providers\TrackingProviderFactory;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateTrackingIntegration extends CreateRecord
{
    protected static string $resource = TrackingIntegrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate provider-specific settings
        if (!empty($data['provider']) && !empty($data['settings'])) {
            $errors = TrackingProviderFactory::validateSettings($data['provider'], $data['settings']);

            if (!empty($errors)) {
                Notification::make()
                    ->title('Validation Error')
                    ->body(implode(' ', $errors))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

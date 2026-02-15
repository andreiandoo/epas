<?php

namespace App\Filament\Tenant\Resources\AdsServiceRequestResource\Pages;

use App\Filament\Tenant\Resources\AdsServiceRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateAdsServiceRequest extends CreateRecord
{
    protected static string $resource = AdsServiceRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Campaign Request Submitted!')
            ->body('Our team will review your request and get back to you shortly.')
            ->persistent()
            ->send();
    }
}

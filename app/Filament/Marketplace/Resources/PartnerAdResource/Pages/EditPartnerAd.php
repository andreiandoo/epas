<?php

namespace App\Filament\Marketplace\Resources\PartnerAdResource\Pages;

use App\Filament\Marketplace\Resources\PartnerAdResource;
use App\Models\PartnerAd;
use App\Services\Partners\PartnerAds;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerAd extends EditRecord
{
    protected static string $resource = PartnerAdResource::class;

    /**
     * The format select holds "zone|format".
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['format'] = ($data['slot'] ?? '') . '|' . ($data['format'] ?? '');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // The partner field is disabled on edit and not submitted.
        $data['marketplace_partner_id'] = $this->record->marketplace_partner_id;

        return PartnerAdResource::prepareForSave($data);
    }

    protected function afterSave(): void
    {
        app(PartnerAds::class)->sync($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pause')
                ->label('Pune pe pauză')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn () => $this->record->status === PartnerAd::STATUS_ACTIVE)
                ->action(function () {
                    PartnerAdResource::changeStatus($this->record, PartnerAd::STATUS_PAUSED);
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('activate')
                ->label('Activează')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->status !== PartnerAd::STATUS_ACTIVE)
                ->action(function () {
                    PartnerAdResource::changeStatus($this->record, PartnerAd::STATUS_ACTIVE);
                    $this->refreshFormData(['status']);
                }),
            Actions\DeleteAction::make()
                ->modalDescription('Reclama se retrage și de pe site-ul partenerului.')
                ->before(fn () => app(PartnerAds::class)->withdrawBeforeDelete($this->record)),
        ];
    }
}

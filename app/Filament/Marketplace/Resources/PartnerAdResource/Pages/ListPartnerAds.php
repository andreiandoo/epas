<?php

namespace App\Filament\Marketplace\Resources\PartnerAdResource\Pages;

use App\Filament\Marketplace\Resources\PartnerAdResource;
use App\Services\Partners\PartnerAds;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPartnerAds extends ListRecords
{
    protected static string $resource = PartnerAdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFormats')
                ->label('Actualizează zonele')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $partners = PartnerAdResource::adPartners();

                    if ($partners->isEmpty()) {
                        Notification::make()
                            ->title('Niciun partener nu are adresa API pentru reclame')
                            ->body('Completeaz-o în Parteneri media → Reclame.')
                            ->warning()
                            ->send();

                        return;
                    }

                    foreach ($partners as $partner) {
                        try {
                            $count = app(PartnerAds::class)->syncFormats($partner);
                            Notification::make()
                                ->title("{$partner->name}: {$count} modele de reclamă")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title("{$partner->name}: zonele nu s-au putut citi")
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}

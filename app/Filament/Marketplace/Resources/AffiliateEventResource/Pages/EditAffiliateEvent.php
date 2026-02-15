<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateEvent extends EditRecord
{
    protected static string $resource = AffiliateEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_affiliate')
                ->label('Deschide pe sursă')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('info')
                ->url(fn () => $this->record->affiliate_url)
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->affiliate_url)),
            Actions\DeleteAction::make(),
        ];
    }
}

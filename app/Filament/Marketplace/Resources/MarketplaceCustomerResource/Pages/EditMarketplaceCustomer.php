<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceCustomer extends EditRecord
{
    protected static string $resource = MarketplaceCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewProfile')
                ->label('Profil Client')
                ->icon('heroicon-o-user-circle')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}

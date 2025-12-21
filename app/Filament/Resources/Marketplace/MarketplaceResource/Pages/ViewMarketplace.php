<?php

namespace App\Filament\Resources\Marketplace\MarketplaceResource\Pages;

use App\Filament\Resources\Marketplace\MarketplaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplace extends ViewRecord
{
    protected static string $resource = MarketplaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->action(fn () => $this->record->update(['status' => 'active']))
                ->requiresConfirmation()
                ->modalHeading('Approve Marketplace')
                ->modalDescription('Are you sure you want to approve this marketplace? They will be able to start accepting organizers.'),
            Actions\Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-pause')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'active')
                ->action(fn () => $this->record->update(['status' => 'suspended']))
                ->requiresConfirmation()
                ->modalHeading('Suspend Marketplace')
                ->modalDescription('Are you sure you want to suspend this marketplace? All their operations will be paused.'),
        ];
    }
}

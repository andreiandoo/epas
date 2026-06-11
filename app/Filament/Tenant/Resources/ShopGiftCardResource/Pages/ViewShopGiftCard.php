<?php

namespace App\Filament\Tenant\Resources\ShopGiftCardResource\Pages;

use App\Filament\Tenant\Resources\ShopGiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopGiftCard extends ViewRecord
{
    protected static string $resource = ShopGiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('add_credit')
                ->label('Add Credit')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'active')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount_cents')
                        ->label('Amount (cents)')
                        ->required()
                        ->numeric()
                        ->minValue(1),
                    \Filament\Forms\Components\TextInput::make('description')
                        ->label('Description')
                        ->placeholder('Reason for credit'),
                ])
                ->action(function (array $data) {
                    $this->record->credit($data['amount_cents'], $data['description'] ?? 'Manual credit');
                }),
        ];
    }
}

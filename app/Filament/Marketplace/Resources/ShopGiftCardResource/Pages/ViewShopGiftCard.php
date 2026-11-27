<?php

namespace App\Filament\Marketplace\Resources\ShopGiftCardResource\Pages;

use App\Filament\Marketplace\Resources\ShopGiftCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopGiftCard extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopGiftCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('add_credit')
                ->label(__('Add Credit'))
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'active')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount_cents')
                        ->label(__('Amount (cents)'))
                        ->required()
                        ->numeric()
                        ->minValue(1),
                    \Filament\Forms\Components\TextInput::make('description')
                        ->label(__('Description'))
                        ->placeholder(__('Reason for credit')),
                ])
                ->action(function (array $data) {
                    $this->record->credit($data['amount_cents'], $data['description'] ?? 'Manual credit');
                }),
        ];
    }
}

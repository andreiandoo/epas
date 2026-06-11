<?php

namespace App\Filament\Tenant\Resources\ShopOrderResource\Pages;

use App\Filament\Tenant\Resources\ShopOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopOrder extends ViewRecord
{
    protected static string $resource = ShopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('mark_shipped')
                ->label('Mark Shipped')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, ['paid', 'processing']) && $this->record->fulfillment_status !== 'fulfilled')
                ->form([
                    \Filament\Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->maxLength(100),
                    \Filament\Forms\Components\TextInput::make('tracking_url')
                        ->label('Tracking URL')
                        ->url(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'shipped',
                        'shipped_at' => now(),
                        'tracking_number' => $data['tracking_number'] ?? null,
                        'tracking_url' => $data['tracking_url'] ?? null,
                    ]);
                }),
            Actions\Action::make('mark_delivered')
                ->label('Mark Delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'shipped')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'delivered',
                        'fulfillment_status' => 'fulfilled',
                        'delivered_at' => now(),
                    ]);
                }),
        ];
    }
}

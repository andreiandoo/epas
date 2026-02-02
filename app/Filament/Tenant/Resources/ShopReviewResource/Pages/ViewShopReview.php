<?php

namespace App\Filament\Tenant\Resources\ShopReviewResource\Pages;

use App\Filament\Tenant\Resources\ShopReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopReview extends ViewRecord
{
    protected static string $resource = ShopReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'approved')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'approved']);
                    $this->record->product?->updateReviewStats();
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status !== 'rejected')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'rejected']);
                    $this->record->product?->updateReviewStats();
                }),
        ];
    }
}

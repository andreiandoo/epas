<?php

namespace App\Filament\Resources\Shorts\ShortPromotionResource\Pages;

use App\Filament\Resources\Shorts\ShortPromotionResource;
use App\Models\ShortPromotion;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditShortPromotion extends EditRecord
{
    protected static string $resource = ShortPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (ShortPromotion $record) => $record->status !== ShortPromotion::STATUS_ACTIVE)
                ->action(fn (ShortPromotion $record) => $record->update([
                    'status' => ShortPromotion::STATUS_ACTIVE,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ])),

            Action::make('pause')
                ->label('Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (ShortPromotion $record) => $record->status === ShortPromotion::STATUS_ACTIVE)
                ->action(fn (ShortPromotion $record) => $record->update(['status' => ShortPromotion::STATUS_PAUSED])),
        ];
    }
}

<?php

namespace App\Filament\Tenant\Resources\ShortPromotionResource\Pages;

use App\Filament\Tenant\Resources\ShortPromotionResource;
use App\Models\ShortPromotion;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditShortPromotion extends EditRecord
{
    protected static string $resource = ShortPromotionResource::class;

    /**
     * Pausing is the one status change an organiser owns.
     *
     * Stopping your own spend must not need our approval — the review gate
     * exists to keep unreviewed ads off the feed, not to keep an advertiser's
     * money on it. Resuming goes back through `active` only if the flight was
     * already approved; a rejected or pending campaign cannot be resumed here.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pause')
                ->label('Pune pe pauză')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (ShortPromotion $record) => $record->status === ShortPromotion::STATUS_ACTIVE)
                ->action(fn (ShortPromotion $record) => $record->update(['status' => ShortPromotion::STATUS_PAUSED])),

            Action::make('resume')
                ->label('Reia')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (ShortPromotion $record) => $record->status === ShortPromotion::STATUS_PAUSED
                    && $record->approved_at !== null)
                ->action(fn (ShortPromotion $record) => $record->update(['status' => ShortPromotion::STATUS_ACTIVE])),
        ];
    }

    /**
     * Editing the creative or the budget of a live flight sends it back for
     * review. Otherwise "approved" would only ever describe the version we
     * happened to look at.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        $material = ['short_id', 'objective', 'bid_cents', 'budget_cents', 'targeting'];

        foreach ($material as $field) {
            if (($data[$field] ?? null) != $record->getAttribute($field)) {
                $data['status'] = ShortPromotion::STATUS_PENDING;
                $data['approved_at'] = null;
                break;
            }
        }

        return $data;
    }
}

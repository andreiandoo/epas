<?php

namespace App\Filament\Resources\Shorts\ShortPromotionResource\Pages;

use App\Filament\Resources\Shorts\ShortPromotionResource;
use App\Models\ShortPromotion;
use Filament\Resources\Pages\CreateRecord;

class CreateShortPromotion extends CreateRecord
{
    protected static string $resource = ShortPromotionResource::class;

    /**
     * Even a campaign created by core admin starts pending.
     *
     * Creating and approving are different decisions, and collapsing them means
     * nobody ever reviews the ads we sell ourselves — which are exactly the ones
     * with no second pair of eyes on them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ShortPromotion::STATUS_PENDING;

        return $data;
    }
}

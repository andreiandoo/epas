<?php

namespace App\Filament\Marketplace\Resources\ShopReviewResource\Pages;

use App\Filament\Marketplace\Resources\ShopReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopReview extends EditRecord
{
    protected static string $resource = ShopReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['admin_response']) && empty($this->record->responded_at)) {
            $data['responded_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->product?->updateReviewStats();
    }
}

<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Resources\CouponCodeResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateCouponCode extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = CouponCodeResource::class;

    public function mount(): void
    {
        parent::mount();

        // Pre-fill from query parameters (e.g. when coming from event edit page)
        $eventId = request()->query('event_id');
        $organizerId = request()->query('organizer_id');

        if ($eventId || $organizerId) {
            $fillData = [];
            if ($eventId) {
                $fillData['applicable_events'] = [(int) $eventId];
            }
            if ($organizerId) {
                $fillData['marketplace_organizer_id'] = (int) $organizerId;
            }
            $this->form->fill(array_merge($this->form->getRawState(), $fillData));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}

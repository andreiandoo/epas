<?php

namespace App\Filament\Tenant\Resources\AffiliateResource\Pages;

use App\Filament\Tenant\Resources\AffiliateResource;
use App\Services\AffiliateTrackingService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliate extends ViewRecord
{
    protected static string $resource = AffiliateResource::class;

    protected string $view = 'filament.tenant.pages.view-affiliate';

    protected function hasInfolist(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getViewData(): array
    {
        $stats = app(AffiliateTrackingService::class)->getAffiliateStats($this->record->id);
        $coupon = $this->record->coupons()->where('active', true)->first();

        return [
            'affiliate' => $this->record,
            'stats' => $stats,
            'coupon' => $coupon,
            'trackingUrl' => url('/') . '?aff=' . $this->record->code,
        ];
    }
}

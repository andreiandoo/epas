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

        // Get tenant's primary domain for tracking URL
        $tenant = auth()->user()->tenant;
        $primaryDomain = $tenant->domains()->where('is_primary', true)->first();
        $baseUrl = $primaryDomain ? 'https://' . $primaryDomain->domain : url('/');

        return [
            'affiliate' => $this->record,
            'stats' => $stats,
            'coupon' => $coupon,
            'trackingUrl' => $baseUrl . '?aff=' . $this->record->code,
        ];
    }
}

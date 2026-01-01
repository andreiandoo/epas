<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PaymentConfig extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Processor';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.marketplace.pages.payment-config';

    public ?MarketplaceClient $marketplace = null;

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

        if (!$this->marketplace) {
            abort(404);
        }
    }

    public function getTitle(): string
    {
        return 'Payment Processor';
    }

    public function getViewData(): array
    {
        $settings = $this->marketplace?->settings ?? [];
        $paymentSettings = $settings['payment'] ?? [];

        return [
            'marketplace' => $this->marketplace,
            'paymentSettings' => $paymentSettings,
            'message' => 'Payment processor configuration is managed at the platform level. Contact support to set up payment processing for your marketplace.',
        ];
    }
}

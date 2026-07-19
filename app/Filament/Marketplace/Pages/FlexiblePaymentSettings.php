<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Services\Installments\ProcessorResolver;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Flexible-payment settings: enable/disable the three sub-modules, view the
 * platform fee and provider tokenization status, and tune reminder cadence.
 * Sub-module toggles persist to the microservice pivot settings the eligibility
 * service reads.
 */
class FlexiblePaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Setări plăți flexibile';
    protected static \UnitEnum|string|null $navigationGroup = 'Plăți flexibile';
    protected static ?int $navigationSort = 40;
    protected string $view = 'filament.marketplace.pages.flexible-payment-settings';

    public bool $enable_installments = true;
    public bool $enable_bnpl = true;
    public bool $enable_delegated_pay = true;
    public array $info = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    public function mount(): void
    {
        $client = static::getMarketplaceClient();
        $pivot = $client?->microservices()->where('slug', 'flexible-payments')->first()?->pivot;
        $settings = $pivot?->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }

        $this->enable_installments = (bool) ($settings['enable_installments'] ?? true);
        $this->enable_bnpl = (bool) ($settings['enable_bnpl'] ?? true);
        $this->enable_delegated_pay = (bool) ($settings['enable_delegated_pay'] ?? true);

        $tokenizable = $client ? app(ProcessorResolver::class)->tokenizableForMarketplaceClient($client) !== null : false;

        $this->info = [
            'platform_fee' => config('installments.platform_fee_percent_installments', 2.0),
            'tokenizable' => $tokenizable,
            'provider' => $client?->getDefaultPaymentMethod()?->slug ?? '—',
            'reminder_days' => implode(', ', (array) config('installments.reminder_days_before', [7, 3, 1])),
            'max_days' => config('installments.max_installment_duration_days', 93),
            'bnpl_days' => config('installments.bnpl_max_horizon_days', 30),
            'delegated_hours' => config('installments.delegated_hold_hours', 24),
        ];
    }

    public function save(): void
    {
        $client = static::getMarketplaceClient();
        $ms = $client?->microservices()->where('slug', 'flexible-payments')->first();
        if (! $ms) {
            Notification::make()->danger()->title('Microserviciu inactiv')->send();
            return;
        }

        $settings = $ms->pivot->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        $settings['enable_installments'] = $this->enable_installments;
        $settings['enable_bnpl'] = $this->enable_bnpl;
        $settings['enable_delegated_pay'] = $this->enable_delegated_pay;

        // Match the repo convention (see SmsNotifications): the pivot `settings`
        // JSON column is written as an encoded string via updateExistingPivot.
        $client->microservices()->updateExistingPivot($ms->id, ['settings' => json_encode($settings)]);

        Notification::make()->success()->title('Setări salvate')->send();
    }
}

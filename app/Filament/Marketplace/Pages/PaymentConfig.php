<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceClientMicroservice;
use App\Models\Microservice;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class PaymentConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Methods';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.marketplace.pages.payment-config';

    public ?MarketplaceClient $marketplace = null;
    public ?array $paymentMethods = [];
    public ?int $editingPaymentMethodId = null;
    public ?array $formData = [];

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;

        if (!$this->marketplace) {
            abort(404);
        }

        $this->loadPaymentMethods();
    }

    protected function loadPaymentMethods(): void
    {
        $this->paymentMethods = $this->marketplace->microservices()
            ->where('category', 'payment')
            ->orderByPivot('sort_order')
            ->get()
            ->map(function ($ms) {
                // Handle settings that might be stored as JSON string (legacy data)
                $settings = $ms->pivot->settings ?? [];
                if (is_string($settings)) {
                    $settings = json_decode($settings, true) ?? [];
                }

                return [
                    'id' => $ms->id,
                    'name' => $ms->getTranslation('name', app()->getLocale()),
                    'slug' => $ms->slug,
                    'icon' => $ms->icon_image,
                    'description' => $ms->getTranslation('short_description', app()->getLocale()),
                    'is_active' => (bool) $ms->pivot->is_active,
                    'is_default' => (bool) $ms->pivot->is_default,
                    'status' => $ms->pivot->status,
                    'settings' => $settings,
                    'settings_schema' => $ms->metadata['settings_schema'] ?? [],
                    'settings_sections' => $ms->metadata['settings_sections'] ?? [],
                    'is_configured' => $this->isPaymentMethodConfigured($ms),
                ];
            })
            ->toArray();
    }

    protected function isPaymentMethodConfigured(Microservice $ms): bool
    {
        $schema = $ms->metadata['settings_schema'] ?? [];
        $settings = $ms->pivot->settings ?? [];

        // Handle settings that might be stored as JSON string (legacy data)
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        }

        foreach ($schema as $field) {
            if (($field['required'] ?? false) && empty($settings[$field['key']] ?? null)) {
                return false;
            }
        }
        return true;
    }

    public function editPaymentMethod(int $id): void
    {
        $this->editingPaymentMethodId = $id;

        $paymentMethod = collect($this->paymentMethods)->firstWhere('id', $id);
        if ($paymentMethod) {
            $this->formData = [
                'is_active' => $paymentMethod['is_active'],
                'is_default' => $paymentMethod['is_default'],
                'settings' => $paymentMethod['settings'],
            ];
        }
    }

    public function cancelEdit(): void
    {
        $this->editingPaymentMethodId = null;
        $this->formData = [];
    }

    public function savePaymentMethod(): void
    {
        if (!$this->editingPaymentMethodId) {
            return;
        }

        $data = $this->formData;

        // Use the MarketplaceClientMicroservice model directly for proper JSON casting
        $pivotRecord = MarketplaceClientMicroservice::where('marketplace_client_id', $this->marketplace->id)
            ->where('microservice_id', $this->editingPaymentMethodId)
            ->first();

        if ($pivotRecord) {
            $pivotRecord->update([
                'is_active' => $data['is_active'] ?? false,
                'is_default' => $data['is_default'] ?? false,
                'status' => ($data['is_active'] ?? false) ? 'active' : 'inactive',
                'settings' => $data['settings'] ?? [],
            ]);
        }

        // If this is set as default, unset others
        if ($data['is_default'] ?? false) {
            MarketplaceClientMicroservice::where('marketplace_client_id', $this->marketplace->id)
                ->where('microservice_id', '!=', $this->editingPaymentMethodId)
                ->whereHas('microservice', fn($q) => $q->where('category', 'payment'))
                ->update(['is_default' => false]);
        }

        Notification::make()
            ->title('Payment method saved')
            ->success()
            ->send();

        $this->editingPaymentMethodId = null;
        $this->formData = [];
        $this->loadPaymentMethods();
    }

    public function getTitle(): string
    {
        return 'Payment Methods';
    }

    public function getEditingPaymentMethod(): ?array
    {
        if (!$this->editingPaymentMethodId) {
            return null;
        }
        return collect($this->paymentMethods)->firstWhere('id', $this->editingPaymentMethodId);
    }

    public function getViewData(): array
    {
        return [
            'marketplace' => $this->marketplace,
            'paymentMethods' => $this->paymentMethods,
            'editingPaymentMethod' => $this->getEditingPaymentMethod(),
            'hasPaymentMethods' => count($this->paymentMethods) > 0,
        ];
    }
}

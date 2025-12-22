<?php

namespace App\Filament\Pages;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Services\Tax\TaxService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use BackedEnum;

class Taxes extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Taxes';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.taxes';

    protected static ?string $title = 'Tax Management';

    protected static ?string $slug = 'taxes';

    public array $summary = [];
    public array $issues = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $taxService = app(TaxService::class);
        $this->summary = $taxService->getTaxSummary($tenant->id);
        $this->issues = $taxService->validateTaxConfiguration($tenant->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createGeneralTax')
                ->label('Add General Tax')
                ->icon('heroicon-o-plus')
                ->url(route('filament.tenant.resources.general-taxes.create'))
                ->color('primary'),

            Action::make('createLocalTax')
                ->label('Add Local Tax')
                ->icon('heroicon-o-map-pin')
                ->url(route('filament.tenant.resources.local-taxes.create'))
                ->color('success'),
        ];
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        $recentGeneralTaxes = GeneralTax::forTenant($tenant->id)
            ->with('eventType')
            ->latest()
            ->limit(5)
            ->get();

        $recentLocalTaxes = LocalTax::forTenant($tenant->id)
            ->with('eventTypes')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'summary' => $this->summary,
            'issues' => $this->issues,
            'recentGeneralTaxes' => $recentGeneralTaxes,
            'recentLocalTaxes' => $recentLocalTaxes,
            'tenantLanguage' => $tenantLanguage,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) {
            return null;
        }

        $count = GeneralTax::forTenant($tenant->id)->active()->count()
               + LocalTax::forTenant($tenant->id)->active()->count();

        return $count > 0 ? (string) $count : null;
    }
}

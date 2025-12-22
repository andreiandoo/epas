<?php

namespace App\Filament\Pages;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use Filament\Pages\Page;
use Filament\Actions\Action;
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
        // Core admin - show global tax summary
        $this->summary = [
            'total_active' => GeneralTax::active()->count() + LocalTax::active()->count(),
            'general' => [
                'active' => GeneralTax::active()->count(),
                'inactive' => GeneralTax::where('is_active', false)->count(),
                'total' => GeneralTax::count(),
            ],
            'local' => [
                'active' => LocalTax::active()->count(),
                'inactive' => LocalTax::where('is_active', false)->count(),
                'total' => LocalTax::count(),
            ],
        ];
        $this->issues = [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createGeneralTax')
                ->label('Add General Tax')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.general-taxes.create'))
                ->color('primary'),

            Action::make('createLocalTax')
                ->label('Add Local Tax')
                ->icon('heroicon-o-map-pin')
                ->url(route('filament.admin.resources.local-taxes.create'))
                ->color('success'),
        ];
    }

    public function getViewData(): array
    {
        $recentGeneralTaxes = GeneralTax::with('eventType')
            ->latest()
            ->limit(5)
            ->get();

        $recentLocalTaxes = LocalTax::with('eventTypes')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'summary' => $this->summary,
            'issues' => $this->issues,
            'recentGeneralTaxes' => $recentGeneralTaxes,
            'recentLocalTaxes' => $recentLocalTaxes,
            'tenantLanguage' => 'en',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = GeneralTax::active()->count() + LocalTax::active()->count();

        return $count > 0 ? (string) $count : null;
    }
}

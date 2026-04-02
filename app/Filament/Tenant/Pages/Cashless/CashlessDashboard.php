<?php

namespace App\Filament\Tenant\Pages\Cashless;

use App\Filament\Tenant\Widgets\Cashless\CashlessKpiCards;
use App\Filament\Tenant\Widgets\Cashless\HourlySalesChart;
use App\Filament\Tenant\Widgets\Cashless\SalesByCategoryChart;
use App\Filament\Tenant\Widgets\Cashless\TopVendorsChart;
use App\Models\FestivalEdition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CashlessDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Cashless Dashboard';

    protected static ?string $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.tenant.pages.cashless.dashboard';

    public ?int $editionId = null;

    public function mount(): void
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? null;

        // Auto-select active edition
        $edition = FestivalEdition::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')
            ->latest('start_date')
            ->first();

        $this->editionId = $edition?->id;
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->editionId) {
            return [];
        }

        return [
            CashlessKpiCards::make(['editionId' => $this->editionId]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->editionId) {
            return [];
        }

        return [
            HourlySalesChart::make(['editionId' => $this->editionId]),
            SalesByCategoryChart::make(['editionId' => $this->editionId]),
            TopVendorsChart::make(['editionId' => $this->editionId]),
        ];
    }

    public function getEditions(): array
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? null;

        return FestivalEdition::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('start_date')
            ->pluck('name', 'id')
            ->toArray();
    }
}

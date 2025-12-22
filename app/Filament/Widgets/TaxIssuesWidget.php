<?php

namespace App\Filament\Widgets;

use App\Services\Tax\TaxService;
use Filament\Widgets\Widget;

class TaxIssuesWidget extends Widget
{
    protected string $view = 'filament.widgets.tax-issues-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->tenant !== null;
    }

    public function getIssues(): array
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return [];
        }

        $taxService = app(TaxService::class);
        return $taxService->validateTaxConfiguration($tenant->id);
    }

    public function getExpiringTaxes(): array
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return [];
        }

        $taxService = app(TaxService::class);
        return $taxService->getExpiringTaxes($tenant->id, 30);
    }
}

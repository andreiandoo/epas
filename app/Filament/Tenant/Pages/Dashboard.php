<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.tenant.pages.dashboard';

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $this->tenant = auth()->user()->tenant;
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function getViewData(): array
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return [
                'tenant' => null,
                'stats' => [],
            ];
        }

        return [
            'tenant' => $tenant,
            'stats' => [
                'domains' => $tenant->domains()->count(),
                'active_domains' => $tenant->domains()->where('is_active', true)->count(),
                'microservices' => $tenant->microservices()->wherePivot('status', 'active')->count(),
                'invoices' => $tenant->invoices()->count(),
                'unpaid_invoices' => $tenant->invoices()->whereIn('status', ['pending', 'overdue'])->count(),
            ],
        ];
    }
}

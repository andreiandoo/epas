<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class Invoices extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoices';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.tenant.pages.invoices';

    public function getTitle(): string
    {
        return 'Invoices';
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return ['invoices' => collect()];
        }

        $invoices = $tenant->invoices()->orderBy('issue_date', 'desc')->get();

        return [
            'invoices' => $invoices,
        ];
    }
}

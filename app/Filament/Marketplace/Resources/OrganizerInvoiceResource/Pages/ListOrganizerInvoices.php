<?php

namespace App\Filament\Marketplace\Resources\OrganizerInvoiceResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerInvoiceResource;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrganizerInvoices extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    use HasMarketplaceContext;

    protected static string $resource = OrganizerInvoiceResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->listHeading(__('Facturi'));
    }

    protected function getHeaderActions(): array
    {
        // Bulk "Generează Facturi" was removed on 2026-08-04 after it
        // silently emitted every organizer invoice on the marketplace
        // when clicked once. The correct flow is per-decont via
        // ViewPayout::generate_invoice_organizer, which knows the exact
        // breakdown (POS + online-included + refunded − kept) for the
        // specific payout / event. See commit that introduced this
        // change for the incident context.
        return [];
    }

    protected function listTodoTabKeys(): array
    {
        return ['unvalidated', 'fiscal'];
    }

    protected function listTodoTabsLabel(): string
    {
        return __('Tip document');
    }

    public function getTabs(): array
    {
        // Same SQL as the "Tip" filter and the Oblio model helpers (meta.accounting.*_at).
        $base = fn () => $this->getResource()::getEloquentQuery();
        $proforma = fn (Builder $q) => $q->whereNull('meta->accounting->external_ref')->whereNotNull('meta->accounting_proforma->external_ref');
        $unvalidated = fn (Builder $q) => $q->whereNull('meta->accounting->external_ref')->whereNull('meta->accounting_proforma->external_ref');
        $fiscal = fn (Builder $q) => $q->whereNotNull('meta->accounting->external_ref');
        $problems = fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->whereNotNull('meta->accounting->stornoed_at')
            ->orWhereNotNull('meta->accounting->canceled_at')
            ->orWhereNotNull('meta->accounting->deleted_at'));

        return [
            'all' => Tab::make(__('Toate'))
                ->badge(fn () => $base()->count())
                ->badgeColor('gray'),
            'outstanding' => Tab::make(__('Neachitate'))
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'outstanding'))
                ->badge(fn () => $base()->where('status', 'outstanding')->count())
                ->badgeColor('warning'),
            'paid' => Tab::make(__('Achitate'))
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'paid'))
                ->badge(fn () => $base()->where('status', 'paid')->count())
                ->badgeColor('success'),
            'proforma' => Tab::make(__('Proforma'))
                ->modifyQueryUsing($proforma)
                ->badge(fn () => $proforma($base())->count())
                ->badgeColor('warning'),
            'oblio_problems' => Tab::make(__('Probleme Oblio'))
                ->modifyQueryUsing($problems)
                ->badge(fn () => $problems($base())->count())
                ->badgeColor('danger'),
            'unvalidated' => Tab::make(__('Nevalidate'))
                ->modifyQueryUsing($unvalidated)
                ->badge(fn () => $unvalidated($base())->count())
                ->badgeColor('gray'),
            'fiscal' => Tab::make(__('Fiscale'))
                ->modifyQueryUsing($fiscal)
                ->badge(fn () => $fiscal($base())->count())
                ->badgeColor('info'),
        ];
    }
}

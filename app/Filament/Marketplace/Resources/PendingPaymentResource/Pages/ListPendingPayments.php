<?php

namespace App\Filament\Marketplace\Resources\PendingPaymentResource\Pages;

use App\Filament\Marketplace\Resources\PendingPaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPendingPayments extends ListRecords
{
    protected static string $resource = PendingPaymentResource::class;

    public function getTitle(): string
    {
        return 'De plătit';
    }

    public function getHeading(): string
    {
        return 'De plătit';
    }

    public function getSubheading(): ?string
    {
        return 'Deconturile care necesită transfer către organizatori. Comută între tab-urile de status pentru a filtra rapid.';
    }

    /**
     * Push the tabs nav into the table toolbar so the status pills sit
     * directly above the rows, alongside the search/filter controls —
     * same trick PayoutResource uses.
     */
    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => {
                    const toolbar = document.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;
                    const nav = \$el.querySelector('.fi-tabs');
                    if (!nav) return;
                    nav.style.order = '-1';
                    toolbar.prepend(nav);
                })",
            ]);
    }

    /**
     * Status tabs above the table. "În așteptare" first → it's the default
     * Filament selects automatically. Each tab carries a count badge so the
     * operator sees the queue size at a glance.
     */
    public function getTabs(): array
    {
        $resource = $this->getResource();

        return [
            'in_asteptare' => Tab::make('În așteptare')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ['pending', 'approved', 'processing']))
                ->badge(fn () => $resource::getEloquentQuery()
                    ->whereIn('status', ['pending', 'approved', 'processing'])
                    ->count())
                ->badgeColor('warning'),

            'achitat' => Tab::make('Achitat')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $resource::getEloquentQuery()
                    ->where('status', 'completed')
                    ->count())
                ->badgeColor('success'),

            'respins' => Tab::make('Respins')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn () => $resource::getEloquentQuery()
                    ->where('status', 'rejected')
                    ->count())
                ->badgeColor('danger'),
        ];
    }
}

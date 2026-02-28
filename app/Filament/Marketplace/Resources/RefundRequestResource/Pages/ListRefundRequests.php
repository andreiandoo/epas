<?php

namespace App\Filament\Marketplace\Resources\RefundRequestResource\Pages;

use App\Filament\Marketplace\Resources\RefundRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListRefundRequests extends ListRecords
{
    protected static string $resource = RefundRequestResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Cereri retur <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => { const header = document.querySelector('.fi-header'); if (!header) return; const actions = header.querySelector('.fi-header-actions-ctn'); if (actions) header.insertBefore(\$el, actions); else header.appendChild(\$el); \$el.style.flex = '1'; \$el.style.minWidth = '0'; const nav = \$el.querySelector('.fi-tabs'); if (nav) { nav.style.marginInline = 'unset'; nav.style.marginLeft = 'auto'; nav.style.marginRight = '0'; } })",
            ]);
    }

    public function getTabs(): array
    {
        return [
            'needs_action' => Tab::make('Needs Action')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'under_review', 'approved']))
                ->badge(fn () => $this->getModel()::query()
                    ->where('marketplace_client_id', RefundRequestResource::getMarketplaceClient()?->id)
                    ->whereIn('status', ['pending', 'under_review', 'approved'])
                    ->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['refunded', 'partially_refunded'])),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),
            'all' => Tab::make('All'),
        ];
    }
}

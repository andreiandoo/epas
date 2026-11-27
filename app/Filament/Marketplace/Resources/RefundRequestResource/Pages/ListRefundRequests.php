<?php

namespace App\Filament\Marketplace\Resources\RefundRequestResource\Pages;

use App\Filament\Marketplace\Resources\RefundRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListRefundRequests extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = RefundRequestResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Cereri retur'));
    }

    /** Same queries as before; only the labels are Romanian and every view has a counter. */
    public function getTabs(): array
    {
        $base = fn () => $this->getResource()::getEloquentQuery();
        $open = ['pending', 'under_review', 'approved'];

        return [
            'needs_action' => Tab::make(__('De rezolvat'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $open))
                ->badge(fn () => $base()->whereIn('status', $open)->count())
                ->badgeColor('warning'),
            'pending' => Tab::make(__('În așteptare'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $base()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make(__('Aprobate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn () => $base()->where('status', 'approved')->count())
                ->badgeColor('success'),
            'completed' => Tab::make(__('Rambursate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['refunded', 'partially_refunded']))
                ->badge(fn () => $base()->whereIn('status', ['refunded', 'partially_refunded'])->count())
                ->badgeColor('success'),
            'rejected' => Tab::make(__('Respinse'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn () => $base()->where('status', 'rejected')->count())
                ->badgeColor('danger'),
            'all' => Tab::make(__('Toate'))
                ->badge(fn () => $base()->count())
                ->badgeColor('gray'),
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources\AttractionResource\Pages;

use App\Filament\Marketplace\Resources\AttractionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListAttractions extends ListRecords
{
    protected static string $resource = AttractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->extraAttributes(['id' => 'create-attraction-btn']),
        ];
    }

    /**
     * Move the filter tabs into the table header toolbar (same layout as Events):
     * tabs/filters on the left, search on the right, no extra header row.
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
                    const createBtn = document.getElementById('create-attraction-btn');
                    if (createBtn) {
                        toolbar.prepend(nav);
                        toolbar.prepend(createBtn);
                        createBtn.style.order = '-2';
                        nav.style.order = '-1';
                    } else {
                        toolbar.prepend(nav);
                        nav.style.order = '-1';
                    }
                    const style = document.createElement('style');
                    style.textContent = '.fi-ta-header-toolbar .fi-ta-bulk-actions { order: -3; }';
                    document.head.appendChild(style);
                })",
            ]);
    }

    public function getTabs(): array
    {
        // The param MUST be named $query: Tab::modifyQuery() applies the modifier
        // via Filament's evaluate(['query' => $query]), which injects by parameter
        // NAME. A param named $q is left null → the active tab 500s. (The default
        // 'all' tab never evaluates a modifier, which is why the list itself loaded.)
        // whereRaw (parenthesised) avoids the where(Closure) path entirely.
        $pg = DB::getDriverName() === 'pgsql';
        $noImage = fn (Builder $query) => $query->whereRaw("(cover_image_url is null or cover_image_url = '')");
        $noCity  = fn (Builder $query) => $query->whereNull('marketplace_city_id');
        $noAddr  = fn (Builder $query) => $query->whereRaw("(address is null or address = '')");
        $noGall  = fn (Builder $query) => $query->whereRaw($pg
            ? "(gallery is null or gallery::text in ('[]', 'null', '{}'))"
            : "(gallery is null or gallery = '[]' or gallery = '')");

        $count = fn (callable $mod) => $mod($this->getResource()::getEloquentQuery())->count();

        return [
            'all'        => Tab::make('Toate'),
            'no_image'   => Tab::make('Fără imagine')
                ->modifyQueryUsing($noImage)
                ->badge(fn () => $count($noImage))
                ->badgeColor('danger'),
            'no_city'    => Tab::make('Fără oraș')
                ->modifyQueryUsing($noCity)
                ->badge(fn () => $count($noCity))
                ->badgeColor('warning'),
            'no_address' => Tab::make('Fără adresă')
                ->modifyQueryUsing($noAddr)
                ->badge(fn () => $count($noAddr))
                ->badgeColor('warning'),
            'no_gallery' => Tab::make('Fără galerie')
                ->modifyQueryUsing($noGall)
                ->badge(fn () => $count($noGall))
                ->badgeColor('gray'),
        ];
    }
}

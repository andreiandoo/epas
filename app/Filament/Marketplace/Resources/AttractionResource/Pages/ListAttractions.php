<?php

namespace App\Filament\Marketplace\Resources\AttractionResource\Pages;

use App\Filament\Marketplace\Resources\AttractionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListAttractions extends ListRecords
{
    protected static string $resource = AttractionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        $noImage = fn (Builder $q) => $q->where(fn ($x) => $x->whereNull('cover_image_url')->orWhere('cover_image_url', ''));
        $noCity  = fn (Builder $q) => $q->whereNull('marketplace_city_id');
        $noAddr  = fn (Builder $q) => $q->where(fn ($x) => $x->whereNull('address')->orWhere('address', ''));
        $noGall  = fn (Builder $q) => $q->where(function ($x) {
            $x->whereNull('gallery');
            if (DB::getDriverName() === 'pgsql') {
                $x->orWhereRaw("gallery::text in ('[]', 'null', '{}')");
            } else {
                $x->orWhere('gallery', '[]')->orWhere('gallery', '');
            }
        });

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

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
        // Use whereRaw (parenthesised) rather than where(Closure): a nested
        // where-closure makes Eloquent call $this->model->newQueryWithoutRelationships(),
        // which 500s inside Filament's table render in some query contexts.
        $pg = DB::getDriverName() === 'pgsql';
        $noImage = fn (Builder $q) => $q->whereRaw("(cover_image_url is null or cover_image_url = '')");
        $noCity  = fn (Builder $q) => $q->whereNull('marketplace_city_id');
        $noAddr  = fn (Builder $q) => $q->whereRaw("(address is null or address = '')");
        $noGall  = fn (Builder $q) => $q->whereRaw($pg
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

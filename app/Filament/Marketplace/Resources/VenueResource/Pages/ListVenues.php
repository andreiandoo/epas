<?php

namespace App\Filament\Marketplace\Resources\VenueResource\Pages;

use App\Filament\Marketplace\Resources\VenueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListVenues extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = VenueResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count(), 0, ',', '.');
        return new HtmlString(e(__('Locații')) . " <span class=\"ep-lst-count\">{$count}</span>");
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Locație nouă'))
                ->icon('heroicon-m-plus')
                ->extraAttributes([
                    'id' => 'create-record-btn',
                ]),
        ];
    }

    /** Vederi pe parteneriat, cu numarul de locatii din fiecare. */
    public function getTabs(): array
    {
        $count = fn (?bool $partner = null) => function () use ($partner) {
            $query = static::getResource()::getEloquentQuery();
            if ($partner === true) {
                $query->where('is_partner', true);
            } elseif ($partner === false) {
                $query->where(fn (Builder $q) => $q->where('is_partner', false)->orWhereNull('is_partner'));
            }

            return number_format($query->count(), 0, ',', '.');
        };

        return [
            'all' => Tab::make(__('Toate'))->badge($count()),
            'partner' => Tab::make(__('Partenere'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_partner', true))
                ->badge($count(true)),
            'non_partner' => Tab::make(__('Nepartenere'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('is_partner', false)->orWhereNull('is_partner')))
                ->badge($count(false)),
        ];
    }
}

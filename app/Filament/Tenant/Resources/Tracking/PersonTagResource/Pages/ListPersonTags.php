<?php

namespace App\Filament\Tenant\Resources\Tracking\PersonTagResource\Pages;

use App\Filament\Tenant\Resources\Tracking\PersonTagResource;
use App\Models\Tracking\PersonTag;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPersonTags extends ListRecords
{
    protected static string $resource = PersonTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tenantId = filament()->getTenant()->id;

        return [
            'all' => Tab::make('All Tags')
                ->badge(PersonTag::forTenant($tenantId)->count()),

            'lifecycle' => Tab::make('Lifecycle')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'lifecycle'))
                ->badge(PersonTag::forTenant($tenantId)->inCategory('lifecycle')->count()),

            'behavior' => Tab::make('Behavior')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'behavior'))
                ->badge(PersonTag::forTenant($tenantId)->inCategory('behavior')->count()),

            'engagement' => Tab::make('Engagement')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'engagement'))
                ->badge(PersonTag::forTenant($tenantId)->inCategory('engagement')->count()),

            'preference' => Tab::make('Preference')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'preference'))
                ->badge(PersonTag::forTenant($tenantId)->inCategory('preference')->count()),

            'custom' => Tab::make('Custom')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('category', 'custom'))
                ->badge(PersonTag::forTenant($tenantId)->inCategory('custom')->count()),
        ];
    }
}

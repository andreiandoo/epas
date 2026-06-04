<?php

namespace App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\Marketplace\OrganizerLead;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrganizerLeads extends ListRecords
{
    protected static string $resource = OrganizerLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Adaugă lead manual')];
    }

    /**
     * Pipeline-state tabs — one click to focus on Mid-funnel / Won / Lost.
     * Counts come from the same scoped query the resource uses so tab
     * badges always agree with the visible row count.
     */
    public function getTabs(): array
    {
        $base = OrganizerLeadResource::getEloquentQuery();

        $count = fn (string $status) => (clone $base)->where('status', $status)->count();

        return [
            'all'           => Tab::make('Toate')->badge($base->count()),
            'new'           => Tab::make('Noi')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_NEW))
                ->badge($count(OrganizerLead::STATUS_NEW))
                ->badgeColor('warning'),
            'contacted'     => Tab::make('Contactate')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_CONTACTED))
                ->badge($count(OrganizerLead::STATUS_CONTACTED)),
            'negotiation'   => Tab::make('Negociere')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    OrganizerLead::STATUS_IN_NEGOTIATION,
                    OrganizerLead::STATUS_DEMO_SCHEDULED,
                ]))
                ->badge(
                    (clone $base)->whereIn('status', [
                        OrganizerLead::STATUS_IN_NEGOTIATION,
                        OrganizerLead::STATUS_DEMO_SCHEDULED,
                    ])->count()
                ),
            'won'           => Tab::make('Acceptate')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_ACCEPTED))
                ->badge($count(OrganizerLead::STATUS_ACCEPTED))
                ->badgeColor('success'),
            'lost'          => Tab::make('Pierdute')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    OrganizerLead::STATUS_REJECTED,
                    OrganizerLead::STATUS_GHOSTED,
                ]))
                ->badge(
                    (clone $base)->whereIn('status', [
                        OrganizerLead::STATUS_REJECTED,
                        OrganizerLead::STATUS_GHOSTED,
                    ])->count()
                ),
        ];
    }
}

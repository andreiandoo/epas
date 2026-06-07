<?php

namespace App\Filament\Marketplace\Resources\OrganizerLeadResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerLeadResource;
use App\Models\Marketplace\OrganizerLead;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
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

        // Filament 4's Tab::badge() prefers a string. Cast int counts so
        // a strict type-juggle in a tagged template doesn't 500 the
        // whole list. Defensive try/catch around each count keeps a
        // broken filter from taking the page down.
        $safeCount = function ($builder) {
            try {
                return (string) $builder->count();
            } catch (\Throwable $e) {
                \Log::warning('OrganizerLead tab count failed', ['error' => $e->getMessage()]);
                return '0';
            }
        };

        return [
            'all' => Tab::make('Toate')->badge($safeCount(clone $base)),
            'new' => Tab::make('Noi')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_NEW))
                ->badge($safeCount((clone $base)->where('status', OrganizerLead::STATUS_NEW)))
                ->badgeColor('warning'),
            'contacted' => Tab::make('Contactate')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_CONTACTED))
                ->badge($safeCount((clone $base)->where('status', OrganizerLead::STATUS_CONTACTED))),
            'negotiation' => Tab::make('Negociere')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    OrganizerLead::STATUS_IN_NEGOTIATION,
                    OrganizerLead::STATUS_DEMO_SCHEDULED,
                ]))
                ->badge($safeCount((clone $base)->whereIn('status', [
                    OrganizerLead::STATUS_IN_NEGOTIATION,
                    OrganizerLead::STATUS_DEMO_SCHEDULED,
                ]))),
            'won' => Tab::make('Acceptate')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', OrganizerLead::STATUS_ACCEPTED))
                ->badge($safeCount((clone $base)->where('status', OrganizerLead::STATUS_ACCEPTED)))
                ->badgeColor('success'),
            'lost' => Tab::make('Pierdute')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    OrganizerLead::STATUS_REJECTED,
                    OrganizerLead::STATUS_GHOSTED,
                ]))
                ->badge($safeCount((clone $base)->whereIn('status', [
                    OrganizerLead::STATUS_REJECTED,
                    OrganizerLead::STATUS_GHOSTED,
                ]))),
        ];
    }
}

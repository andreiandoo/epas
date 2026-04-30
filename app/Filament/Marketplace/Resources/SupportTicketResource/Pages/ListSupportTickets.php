<?php

namespace App\Filament\Marketplace\Resources\SupportTicketResource\Pages;

use App\Filament\Marketplace\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getTabs(): array
    {
        $closedStatuses = [
            SupportTicket::STATUS_RESOLVED,
            SupportTicket::STATUS_CLOSED,
        ];

        return [
            'all' => Tab::make('Toate'),

            'open' => Tab::make('Active')
                ->modifyQueryUsing(fn ($q) => $q->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('warning'),

            'unassigned' => Tab::make('Nealocate')
                ->modifyQueryUsing(fn ($q) => $q
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('danger'),
        ];
    }
}

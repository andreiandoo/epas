<?php

namespace App\Filament\Marketplace\Resources\SupportTicketResource\Pages;

use App\Filament\Marketplace\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getTabs(): array
    {
        $adminId = Auth::guard('marketplace_admin')->id();
        $closedStatuses = [
            SupportTicket::STATUS_RESOLVED,
            SupportTicket::STATUS_CLOSED,
        ];

        // Closure parameter MUST be named `$query` (matches Filament's
        // `evaluate(['query' => $query])`) AND type-hinted Builder, otherwise
        // Filament's reflection-based DI may pass null and the chained
        // ->whereNotIn(...) call crashes.

        return [
            'all' => Tab::make('Toate'),

            'open' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('warning'),

            'unassigned' => Tab::make('Nealocate')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('danger'),

            'mine' => Tab::make('Asignate mie')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('primary'),

            'awaiting_staff' => Tab::make('Așteaptă staff')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    SupportTicket::STATUS_OPEN,
                    SupportTicket::STATUS_IN_PROGRESS,
                ])),

            'closed' => Tab::make('Închise')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $closedStatuses)),
        ];
    }
}

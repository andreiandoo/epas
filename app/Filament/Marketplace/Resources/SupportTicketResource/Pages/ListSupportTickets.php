<?php

namespace App\Filament\Marketplace\Resources\SupportTicketResource\Pages;

use App\Filament\Marketplace\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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

            'mine' => Tab::make('Asignate mie')
                ->modifyQueryUsing(fn ($q) => $q
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => SupportTicketResource::getEloquentQuery()
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('primary'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}

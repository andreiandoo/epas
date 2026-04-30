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

        return [
            'all' => Tab::make('Toate')
                ->badge(fn () => static::badgeCount(fn (Builder $q) => $q)),

            'open' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereNotIn('status', [
                    SupportTicket::STATUS_RESOLVED,
                    SupportTicket::STATUS_CLOSED,
                ]))
                ->badge(fn () => static::badgeCount(fn (Builder $q) => $q->whereNotIn('status', [
                    SupportTicket::STATUS_RESOLVED,
                    SupportTicket::STATUS_CLOSED,
                ])))
                ->badgeColor('warning'),

            'unassigned' => Tab::make('Nealocate')
                ->modifyQueryUsing(fn (Builder $q) => $q
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED]))
                ->badge(fn () => static::badgeCount(fn (Builder $q) => $q
                    ->whereNull('assigned_to_marketplace_admin_id')
                    ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])))
                ->badgeColor('danger'),

            'mine' => Tab::make('Asignate mie')
                ->modifyQueryUsing(fn (Builder $q) => $q
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED]))
                ->badge(fn () => static::badgeCount(fn (Builder $q) => $q
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])))
                ->badgeColor('primary'),

            'awaiting_staff' => Tab::make('Așteaptă staff')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    SupportTicket::STATUS_OPEN,
                    SupportTicket::STATUS_IN_PROGRESS,
                ])),

            'closed' => Tab::make('Închise')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [
                    SupportTicket::STATUS_RESOLVED,
                    SupportTicket::STATUS_CLOSED,
                ])),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }

    /**
     * Run a count using the resource's scoped query so badges respect
     * marketplace_client isolation.
     */
    protected static function badgeCount(\Closure $modifier): ?string
    {
        $query = SupportTicketResource::getEloquentQuery();
        $modifier($query);
        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }
}

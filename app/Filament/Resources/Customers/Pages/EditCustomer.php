<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stats')->label('Stats')->icon('heroicon-o-chart-bar')
                ->url(fn() => static::getResource()::getUrl('stats', ['record' => $this->record])),
            Action::make('seeOrders')->label('See Orders')->icon('heroicon-o-receipt-percent')
                ->url(fn() => route('filament.admin.resources.orders.index').'?tableSearch='.urlencode($this->record->email)),
            Action::make('seeTickets')->label('See Tickets')->icon('heroicon-o-ticket')
                ->url(fn() => route('filament.admin.resources.tickets.index').'?tableSearch='.urlencode($this->record->email)),
            Action::make('seeEvents')->label('See Events')->icon('heroicon-o-calendar')
                ->url(fn() => static::getResource()::getUrl('stats', ['record' => $this->record]).'#events'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustPoints')
                ->label('Ajustează puncte')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('action')
                        ->label('Acțiune')
                        ->options([
                            'add' => 'Adaugă puncte',
                            'subtract' => 'Scade puncte',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('amount')
                        ->label('Cantitate puncte')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    Forms\Components\TextInput::make('reason')
                        ->label('Motiv')
                        ->required()
                        ->placeholder('Ex: Bonus aniversar, Corecție sistem, etc.'),
                ])
                ->action(function (array $data): void {
                    $customer = $this->record;
                    $amount = (int) $data['amount'];
                    $reason = $data['reason'];

                    if ($data['action'] === 'add') {
                        $customer->addPoints($amount, 'adjustment', $reason);
                        Notification::make()
                            ->title('Puncte adăugate')
                            ->body("S-au adăugat {$amount} puncte clientului.")
                            ->success()
                            ->send();
                    } else {
                        if ($amount > $customer->points_balance) {
                            Notification::make()
                                ->title('Eroare')
                                ->body("Clientul are doar {$customer->points_balance} puncte disponibile.")
                                ->danger()
                                ->send();
                            return;
                        }
                        $customer->spendPoints($amount, 'adjustment', $reason);
                        Notification::make()
                            ->title('Puncte scăzute')
                            ->body("S-au scăzut {$amount} puncte din contul clientului.")
                            ->success()
                            ->send();
                    }

                    $this->refreshFormData(['points_balance', 'points_earned', 'points_spent']);
                }),
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

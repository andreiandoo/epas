<?php

namespace App\Filament\Tenant\Resources\ResourceRentalResource\Pages;

use App\Filament\Tenant\Resources\ResourceRentalResource;
use App\Services\Leisure\RentalService;
use Filament\Actions;
use Filament\Infolists\Components as Info;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewResourceRental extends ViewRecord
{
    protected static string $resource = ResourceRentalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('forceEnd')
                ->label('Forțează închidere')
                ->icon('heroicon-o-stop-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record?->ended_at === null)
                ->action(function () {
                    app(RentalService::class)->end($this->record, auth()->id());
                    Notification::make()->success()->title('Rental încheiat')->send();
                    $this->refreshFormData(['ended_at', 'overtime_minutes', 'overtime_surcharge_cents']);
                }),
            Actions\Action::make('markSurchargePaid')
                ->label('Marchează surcharge ca plătit')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record?->ended_at !== null
                    && ($this->record?->overtime_surcharge_cents ?? 0) > 0
                    && ! $this->record?->surcharge_paid)
                ->action(function () {
                    $this->record->update(['surcharge_paid' => true]);
                    Notification::make()->success()->title('Surcharge marcat ca plătit')->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Info\Section::make('Status rental')
                ->columns(3)
                ->schema([
                    Info\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->state(fn ($record) => $record->is_active
                            ? ($record->is_overdue ? 'overdue' : 'active')
                            : 'ended')
                        ->color(fn ($state) => match ($state) {
                            'active' => 'info',
                            'overdue' => 'danger',
                            'ended' => 'success',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'active' => 'Activ',
                            'overdue' => 'În depășire',
                            'ended' => 'Finalizat',
                            default => $state,
                        }),
                    Info\TextEntry::make('current_overtime_minutes')
                        ->label('Depășire (min)')
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                    Info\TextEntry::make('overtime_surcharge_cents')
                        ->label('Surcharge depășire')
                        ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2) . ' RON')
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                ]),

            Info\Section::make('Echipament')
                ->columns(3)
                ->schema([
                    Info\TextEntry::make('physicalResource.name')
                        ->label('Resursă')
                        ->weight('bold'),
                    Info\TextEntry::make('physicalResource.type.name')
                        ->label('Tip')
                        ->badge()
                        ->placeholder('—'),
                    Info\TextEntry::make('physicalResource.qr_code')
                        ->label('QR Code')
                        ->fontFamily('mono')
                        ->copyable(),
                    Info\TextEntry::make('physicalResource.label')
                        ->label('Etichetă')
                        ->placeholder('—'),
                    Info\TextEntry::make('physicalResource.status')
                        ->label('Status echipament')
                        ->badge(),
                ]),

            Info\Section::make('Bilet & client')
                ->columns(3)
                ->schema([
                    Info\TextEntry::make('ticket.code')
                        ->label('Cod bilet')
                        ->fontFamily('mono')
                        ->copyable(),
                    Info\TextEntry::make('ticket.ticketType.name')
                        ->label('Tip bilet'),
                    Info\TextEntry::make('ticket.order.customer_email')
                        ->label('Email client')
                        ->placeholder('—'),
                    Info\TextEntry::make('ticket.order.customer_name')
                        ->label('Nume client')
                        ->placeholder('—'),
                    Info\TextEntry::make('ticket.order.order_number')
                        ->label('Comandă')
                        ->placeholder('—'),
                ]),

            Info\Section::make('Cronologie')
                ->columns(3)
                ->schema([
                    Info\TextEntry::make('started_at')
                        ->label('Pornit la')
                        ->dateTime('d.m.Y H:i'),
                    Info\TextEntry::make('planned_end_at')
                        ->label('Sfârșit planificat')
                        ->dateTime('d.m.Y H:i'),
                    Info\TextEntry::make('ended_at')
                        ->label('Sfârșit real')
                        ->dateTime('d.m.Y H:i')
                        ->placeholder('— în desfășurare'),
                    Info\TextEntry::make('elapsed_minutes')
                        ->label('Durată totală')
                        ->formatStateUsing(fn ($state) => $state . ' min')
                        ->badge(),
                    Info\TextEntry::make('overtime_minutes')
                        ->label('Depășire stocată')
                        ->formatStateUsing(fn ($state) => $state . ' min')
                        ->visible(fn ($record) => $record->ended_at !== null),
                    Info\TextEntry::make('surcharge_paid')
                        ->label('Surcharge plătit')
                        ->badge()
                        ->state(fn ($record) => $record->surcharge_paid ? 'Da' : 'Nu')
                        ->color(fn ($record) => $record->surcharge_paid ? 'success' : 'warning')
                        ->visible(fn ($record) => ($record->overtime_surcharge_cents ?? 0) > 0),
                ]),

            Info\Section::make('Operatori')
                ->columns(2)
                ->schema([
                    Info\TextEntry::make('started_by_user_id')
                        ->label('Pornit de')
                        ->formatStateUsing(fn ($state) => $state ? (\App\Models\User::find($state)?->name ?? "User #{$state}") : '—'),
                    Info\TextEntry::make('ended_by_user_id')
                        ->label('Închis de')
                        ->formatStateUsing(fn ($state) => $state ? (\App\Models\User::find($state)?->name ?? "User #{$state}") : '—'),
                ]),

            Info\Section::make('Note')
                ->visible(fn ($record) => filled($record->notes))
                ->schema([
                    Info\TextEntry::make('notes')->label('')->columnSpanFull(),
                ]),
        ]);
    }
}

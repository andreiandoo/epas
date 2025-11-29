<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record])),

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => $record->tenant
                        ? \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $record->tenant])
                        : null
                    ),

                // Customer display + link la view Customer
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->formatStateUsing(function ($state, $record) {
                        // fallback: email dacă nu avem nume
                        return $state ?: $record->customer?->email ?: $record->customer_email;
                    })
                    ->url(fn ($record) => $record->customer
                        ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer])
                        : null
                    )
                    ->sortable()
                    ->searchable(),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->sortable()
                    ->formatStateUsing(fn ($v) => number_format(($v ?? 0) / 100, 2) . ' RON'),

                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger'  => fn ($state) => in_array($state, ['cancelled', 'failed']),
                        'gray'    => 'refunded',
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                // Event column (via tickets relationship)
                TextColumn::make('event')
                    ->label('Event')
                    ->formatStateUsing(function ($record) {
                        $firstTicket = $record->tickets()->with('ticketType.event')->first();
                        if ($firstTicket && $firstTicket->ticketType && $firstTicket->ticketType->event) {
                            $event = $firstTicket->ticketType->event;
                            $title = is_array($event->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : $event->title;
                            return $title;
                        }
                        return 'N/A';
                    })
                    ->url(function ($record) {
                        $firstTicket = $record->tickets()->with('ticketType.event')->first();
                        if ($firstTicket && $firstTicket->ticketType && $firstTicket->ticketType->event) {
                            return \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $firstTicket->ticketType->event]);
                        }
                        return null;
                    })
                    ->searchable(false)
                    ->sortable(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending'   => 'Pending',
                    'paid'      => 'Paid',
                    'cancelled' => 'Cancelled',
                    'refunded'  => 'Refunded',
                    'failed'    => 'Failed',
                ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

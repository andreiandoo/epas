<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Services\TicketEventResolver;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class OrdersTable
{
    /**
     * Cache resolved events per order to avoid duplicate resolution.
     */
    private static array $eventCache = [];

    /**
     * Resolve event from an order through multiple paths, with caching.
     * Uses TicketEventResolver for ticket-level resolution.
     */
    protected static function resolveOrderEvent($record): ?\App\Models\Event
    {
        $orderId = $record->id;
        if (array_key_exists($orderId, self::$eventCache)) {
            return self::$eventCache[$orderId];
        }

        $event = null;

        // 1. Via ticket → ticketType → event (standard web flow)
        // Use eager-loaded tickets if available
        $firstTicket = $record->relationLoaded('tickets')
            ? $record->tickets->first()
            : $record->tickets()->with(['ticketType.event'])->first();

        if ($firstTicket) {
            $event = TicketEventResolver::resolve($firstTicket);
        }

        // 2. Direct event_id on order
        if (!$event && $record->event_id) {
            $event = \App\Models\Event::find($record->event_id);
        }

        // 3. Marketplace event_id on order
        if (!$event && $record->marketplace_event_id) {
            $event = \App\Models\Event::find($record->marketplace_event_id);
        }

        self::$eventCache[$orderId] = $event;
        return $event;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with([
                'tenant:id,name,public_name',
                'customer:id,first_name,last_name,email',
                'marketplaceClient:id,name',
                'marketplaceOrganizer:id,name',
                'tickets.ticketType:id,event_id,name,marketplace_event_id',
                'tickets.ticketType.event:id,title,event_date,venue_id',
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->formatStateUsing(fn ($state) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT))
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record])),

                TextColumn::make('source_name')
                    ->label('Tenant / Marketplace')
                    ->getStateUsing(function ($record) {
                        // If order has a marketplace_client_id, always show the marketplace name
                        if ($record->marketplace_client_id && $record->marketplaceClient) {
                            return $record->marketplaceClient->name;
                        }
                        // Otherwise show the tenant
                        if ($record->tenant) {
                            return $record->tenant->public_name ?? $record->tenant->name;
                        }
                        return '-';
                    })
                    ->description(function ($record) {
                        if ($record->marketplace_client_id) {
                            // Show organizer/tenant as sub-label for marketplace orders
                            $organizer = $record->marketplaceOrganizer?->name ?? $record->tenant?->public_name ?? $record->tenant?->name;
                            return $organizer ? "Marketplace · {$organizer}" : 'Marketplace';
                        }
                        if ($record->tenant_id) {
                            return 'Tenant';
                        }
                        return null;
                    })
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('customer_display')
                    ->label('Customer')
                    ->getStateUsing(function ($record) {
                        return $record->customer_name
                            ?? $record->customer?->full_name
                            ?? (is_array($record->meta) ? ($record->meta['customer_name'] ?? null) : null)
                            ?? $record->customer?->email
                            ?? $record->customer_email
                            ?? '-';
                    })
                    ->url(fn ($record) => $record->customer
                        ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer])
                        : null
                    )
                    ->searchable(false),

                TextColumn::make('order_total')
                    ->label('Total')
                    ->getStateUsing(function ($record) {
                        $total = $record->total ?? (($record->total_cents ?? 0) / 100);
                        $currency = $record->currency ?? 'RON';
                        return number_format((float) $total, 2) . ' ' . $currency;
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw('COALESCE(total, total_cents / 100.0) ' . $direction);
                    }),

                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['paid', 'confirmed', 'completed']),
                        'danger'  => fn ($state) => in_array($state, ['cancelled', 'failed']),
                        'gray'    => fn ($state) => in_array($state, ['refunded', 'expired']),
                    ])
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'web' => 'primary',
                        'pos', 'app' => 'warning',
                        'api' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                // Event column (multiple resolution paths for web, POS, app orders)
                TextColumn::make('event_name')
                    ->label('Event')
                    ->getStateUsing(function ($record) {
                        $event = static::resolveOrderEvent($record);
                        if (!$event) return '-';
                        $title = is_array($event->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : $event->title;
                        return $title ?: '-';
                    })
                    ->url(function ($record) {
                        $event = static::resolveOrderEvent($record);
                        if (!$event) return null;
                        return \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event]);
                    })
                    ->searchable(false)
                    ->sortable(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending'   => 'Pending',
                    'paid'      => 'Paid',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'refunded'  => 'Refunded',
                    'failed'    => 'Failed',
                    'expired'   => 'Expired',
                ]),
                Tables\Filters\SelectFilter::make('source')->options([
                    'web' => 'Web',
                    'pos' => 'POS',
                    'app' => 'App',
                    'api' => 'API',
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

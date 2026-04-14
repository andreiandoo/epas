<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\TicketResource\Pages;
use App\Models\Ticket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class TicketResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Ticket::class;
    protected static ?string $navigationLabel = 'Bilete';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        $query = parent::getEloquentQuery()
            ->whereHas('ticketType.event', function ($q) use ($marketplace) {
                $q->where('marketplace_client_id', $marketplace?->id);
            });

        // Filter by customer from URL query param
        if ($customerId = request()->query('customer')) {
            $query->whereHas('order', fn ($q) => $q->where('marketplace_customer_id', $customerId));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Cod Bilet')
                    ->icon(fn ($record) => ($record->meta['has_insurance'] ?? false) ? 'heroicon-o-shield-check' : null)
                    ->iconColor('success')
                    ->iconPosition('before')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ticketType.event.title')
                    ->label('Eveniment')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Tip Bilet')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state, $record) =>
                        $state
                            ? '#' . str_pad($state, 6, '0', STR_PAD_LEFT) .
                              ($record->order?->order_number ? " ({$record->order->order_number})" : '')
                            : '-'
                    )
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('order', function ($q) use ($search) {
                            $q->where('id', 'like', "%{$search}%")
                              ->orWhere('order_number', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('beneficiary_name')
                    ->label('Beneficiar')
                    ->getStateUsing(function ($record) {
                        // Check if ticket has beneficiary in meta
                        $meta = $record->meta ?? [];
                        if (!empty($meta['beneficiary']['name'])) {
                            return $meta['beneficiary']['name'];
                        }
                        if (!empty($meta['beneficiary_name'])) {
                            return $meta['beneficiary_name'];
                        }
                        // Check ticket-level attendee fields
                        if (!empty($record->attendee_name)) {
                            return $record->attendee_name;
                        }
                        // Fall back to customer name from order (direct column, then meta, then customer relation)
                        $order = $record->order;
                        if (!$order) return '-';
                        return $order->customer_name
                            ?? ($order->meta['customer_name'] ?? null)
                            ?? $order->marketplaceCustomer?->name
                            ?? $order->customer?->full_name
                            ?? $order->customer_email
                            ?? '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('attendee_name', 'like', "%{$search}%")
                              ->orWhereHas('order', function ($q2) use ($search) {
                                  $q2->where('customer_name', 'like', "%{$search}%")
                                    ->orWhere('customer_email', 'like', "%{$search}%");
                              });
                        });
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('beneficiary_email')
                    ->label('Email')
                    ->getStateUsing(function ($record) {
                        // Check if ticket has beneficiary email in meta (for invitations)
                        $meta = $record->meta ?? [];
                        if (!empty($meta['beneficiary']['email'])) {
                            return $meta['beneficiary']['email'];
                        }
                        // Fall back to customer email from order
                        return $record->order?->customer?->email ?? '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('meta->beneficiary->email', 'like', "%{$search}%")
                              ->orWhereHas('order.customer', function ($q2) use ($search) {
                                  $q2->where('email', 'like', "%{$search}%");
                              });
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'valid',
                        'warning' => 'used',
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'valid' => 'Valid',
                        'used' => 'Utilizat',
                        'cancelled' => 'Anulat',
                        'refunded' => 'Rambursat',
                        default => ucfirst($state),
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('seat_label')
                    ->label('Loc')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'valid' => 'Valid',
                        'used' => 'Utilizat',
                        'cancelled' => 'Anulat',
                        'refunded' => 'Rambursat',
                    ]),
                Tables\Filters\Filter::make('event_id')
                    ->query(fn ($query, array $data) => $query->when(
                        $data['event_id'] ?? null,
                        fn ($q, $eventId) => $q->where('event_id', $eventId)
                    ))
                    ->form([
                        \Filament\Forms\Components\Select::make('event_id')
                            ->label('Eveniment')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $marketplace = static::getMarketplaceClient();
                                $term = '%' . mb_strtolower($search) . '%';
                                $isPgsql = \DB::getDriverName() === 'pgsql';
                                return \App\Models\Event::where('marketplace_client_id', $marketplace?->id)
                                    ->where(function ($q) use ($term, $isPgsql) {
                                        $q->whereRaw($isPgsql ? "LOWER(title::jsonb->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ?", [$term])
                                          ->orWhereRaw($isPgsql ? "LOWER(title::jsonb->>'en') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?", [$term]);
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->getTranslation('title', 'ro') ?: $e->name]);
                            })
                            ->getOptionLabelUsing(fn ($value) => \App\Models\Event::find($value)?->getTranslation('title', 'ro') ?? $value),
                    ]),
                Tables\Filters\TernaryFilter::make('is_invitation')
                    ->label('Tip')
                    ->placeholder('Toate')
                    ->trueLabel('Doar Invitații')
                    ->falseLabel('Doar Comenzi')
                    ->queries(
                        true: fn (Builder $query) => $query->whereJsonContains('meta->is_invitation', true),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('meta->is_invitation')
                              ->orWhereJsonContains('meta->is_invitation', false);
                        }),
                    ),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('change_status')
                        ->label('Schimbă status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status nou')
                                ->options([
                                    'valid' => 'Valid',
                                    'used' => 'Utilizat',
                                    'cancelled' => 'Anulat',
                                ])
                                ->helperText('Statusul Rambursat se setează automat la rambursarea comenzii.')
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $newStatus = $data['status'];
                            $cancelledTickets = collect();

                            $records->each(function ($record) use ($newStatus, &$cancelledTickets) {
                                $oldStatus = $record->status;
                                $record->update(['status' => $newStatus]);

                                // If changing TO cancelled from a non-cancelled status, track for stock release
                                if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                                    $cancelledTickets->push($record);
                                }
                            });

                            // Release stock for newly cancelled tickets
                            if ($cancelledTickets->isNotEmpty()) {
                                $order = $cancelledTickets->first()->order;
                                if ($order) {
                                    $order->releaseStockForTickets($cancelledTickets);
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Status actualizat pentru ' . $records->count() . ' bilete')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulk_delete')
                        ->label('Șterge')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Șterge biletele selectate')
                        ->modalDescription('Biletele valide sau utilizate nu pot fi șterse. Doar biletele anulate sau rambursate vor fi șterse.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $protected = ['valid', 'used'];
                            $deletable = $records->filter(fn ($r) => !in_array($r->status, $protected));
                            $skipped = $records->count() - $deletable->count();
                            $deletable->each(fn ($r) => $r->delete());
                            $msg = $deletable->count() . ' bilete șterse.';
                            if ($skipped > 0) $msg .= " {$skipped} bilete protejate (valide/utilizate) au fost ignorate.";
                            \Filament\Notifications\Notification::make()->title($msg)->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

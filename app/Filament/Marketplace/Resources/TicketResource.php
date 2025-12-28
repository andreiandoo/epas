<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\TicketResource\Pages;
use App\Models\Ticket;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;

        return parent::getEloquentQuery()
            ->whereHas('ticketType.event', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant?->id);
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Cod Bilet')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('ticketType.event.title')
                    ->label('Eveniment')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Tip Bilet')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state) => $state ? '#' . str_pad($state, 6, '0', STR_PAD_LEFT) : '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beneficiary_name')
                    ->label('Beneficiar')
                    ->getStateUsing(function ($record) {
                        // Check if ticket has beneficiary in meta (stored as meta.beneficiary.name)
                        $meta = $record->meta ?? [];
                        if (!empty($meta['beneficiary']['name'])) {
                            return $meta['beneficiary']['name'];
                        }
                        // Also check for legacy format (beneficiary_name directly)
                        if (!empty($meta['beneficiary_name'])) {
                            return $meta['beneficiary_name'];
                        }
                        // Fall back to customer name from order
                        $orderMeta = $record->order?->meta ?? [];
                        return $orderMeta['customer_name'] ?? $record->order?->customer?->full_name ?? '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('order', function ($q) use ($search) {
                            $q->where('meta->customer_name', 'like', "%{$search}%");
                        });
                    }),
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
                    }),
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

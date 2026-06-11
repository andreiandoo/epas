<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\Ticket;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    // Filament v4: BackedEnum|string|null
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';

    // Filament v4: UnitEnum|string|null
    protected static UnitEnum|string|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 20;

    // ✅ Filament v4: Schema în loc de Forms\Form
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('code')
                ->disabled()
                ->label('Code'),

            Forms\Components\Select::make('status')
                ->options([
                    'valid' => 'Valid',
                    'used'  => 'Used',
                    'void'  => 'Void',
                ])
                ->required(),

            Forms\Components\TextInput::make('seat_label')
                ->label('Seat'),

            Forms\Components\Select::make('order_id')
                ->relationship('order', 'id')
                ->label('Order')
                ->searchable(),

            Forms\Components\Select::make('ticket_type_id')
                ->relationship('ticketType', 'name')
                ->label('Ticket type')
                ->required()
                ->searchable(),

            Forms\Components\Select::make('performance_id')
                ->relationship('performance', 'id')
                ->label('Performance')
                ->searchable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->label('Unique Code')
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Ticket Type')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('ticketType.event.title')
                    ->label('Event Name')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return $state['en'] ?? $state['ro'] ?? reset($state);
                        }
                        return $state;
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('ticketType.event.event_date')
                    ->label('Event Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ticketType.event.start_time')
                    ->label('Start Time')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.customer_email')
                    ->label('Client (Email)')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'valid',
                        'gray'    => 'used',
                        'danger'  => 'void',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'valid' => 'Valid',
                    'used'  => 'Used',
                    'void'  => 'Void',
                ]),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Event')
                    ->relationship('ticketType.event', 'title'),
            ])
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view'  => Pages\ViewTicket::route('/{record}'),
            'edit'  => EditTicket::route('/{record}/edit'),
        ];
    }
}

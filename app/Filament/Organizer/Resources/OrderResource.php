<?php

namespace App\Filament\Organizer\Resources;

use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $organizer = auth('organizer')->user()?->organizer;

        return parent::getEloquentQuery()
            ->where('organizer_id', $organizer?->id);
    }

    public static function canCreate(): bool
    {
        return false; // Orders are created by customers
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only form for viewing
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Order ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\TextInput::make('created_at')
                            ->label('Order Date')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Revenue Breakdown')
                    ->schema([
                        Forms\Components\TextInput::make('total')
                            ->label('Order Total')
                            ->disabled()
                            ->suffix('RON'),

                        Forms\Components\TextInput::make('tixello_commission')
                            ->label('Platform Fee (1%)')
                            ->disabled()
                            ->suffix('RON'),

                        Forms\Components\TextInput::make('marketplace_commission')
                            ->label('Marketplace Commission')
                            ->disabled()
                            ->suffix('RON'),

                        Forms\Components\TextInput::make('organizer_revenue')
                            ->label('Your Revenue')
                            ->disabled()
                            ->suffix('RON'),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->prefix('#')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizer_revenue')
                    ->label('Your Revenue')
                    ->money('RON')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('payout_id')
                    ->label('Paid Out')
                    ->boolean()
                    ->getStateUsing(fn (Order $record) => $record->isPaidOut())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\TernaryFilter::make('paid_out')
                    ->label('Paid Out')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('payout_id'),
                        false: fn (Builder $query) => $query->whereNull('payout_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Order ID'),
                        Infolists\Components\TextEntry::make('customer_email')
                            ->label('Customer'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'paid', 'completed' => 'success',
                                'pending' => 'warning',
                                'cancelled', 'refunded' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Order Date')
                            ->dateTime(),
                    ])->columns(4),

                Infolists\Components\Section::make('Revenue Breakdown')
                    ->schema([
                        Infolists\Components\TextEntry::make('total')
                            ->label('Order Total')
                            ->money('RON'),
                        Infolists\Components\TextEntry::make('tixello_commission')
                            ->label('Platform Fee (1%)')
                            ->money('RON'),
                        Infolists\Components\TextEntry::make('marketplace_commission')
                            ->label('Marketplace Commission')
                            ->money('RON'),
                        Infolists\Components\TextEntry::make('organizer_revenue')
                            ->label('Your Revenue')
                            ->money('RON')
                            ->color('success'),
                    ])->columns(4),

                Infolists\Components\Section::make('Payout Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('payout_id')
                            ->label('Paid Out')
                            ->boolean()
                            ->getStateUsing(fn (Order $record) => $record->isPaidOut()),
                        Infolists\Components\TextEntry::make('payout.reference')
                            ->label('Payout Reference')
                            ->placeholder('Not yet paid out'),
                        Infolists\Components\TextEntry::make('payout.processed_at')
                            ->label('Payout Date')
                            ->dateTime()
                            ->placeholder('Pending'),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderResource\RelationManagers\TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => OrderResource\Pages\ListOrders::route('/'),
            'view' => OrderResource\Pages\ViewOrder::route('/{record}'),
        ];
    }
}

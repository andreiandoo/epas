<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as IC;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                IC\Section::make('Detalii comandă')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->schema([
                        IC\TextEntry::make('order_number')
                            ->label('Număr comandă')
                            ->formatStateUsing(fn ($record) => '#' . str_pad($record->id, 6, '0', STR_PAD_LEFT))
                            ->weight('bold')
                            ->size('lg'),
                        IC\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'paid', 'confirmed' => 'success',
                                'cancelled' => 'danger',
                                'refunded' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'În așteptare',
                                'paid' => 'Plătită',
                                'confirmed' => 'Confirmată',
                                'cancelled' => 'Anulată',
                                'refunded' => 'Rambursată',
                                default => ucfirst($state),
                            }),
                        IC\TextEntry::make('total_cents')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . ($record->tickets->first()?->ticketType?->currency ?? 'RON'))
                            ->weight('bold')
                            ->size('lg'),
                        IC\TextEntry::make('created_at')
                            ->label('Data comenzii')
                            ->dateTime('d M Y H:i'),
                        IC\TextEntry::make('meta.payment_method')
                            ->label('Metodă plată')
                            ->default('Card'),
                        IC\TextEntry::make('updated_at')
                            ->label('Ultima actualizare')
                            ->dateTime('d M Y H:i'),
                    ]),

                IC\Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        IC\TextEntry::make('meta.customer_name')
                            ->label('Nume')
                            ->default('N/A'),
                        IC\TextEntry::make('customer_email')
                            ->label('Email')
                            ->url(fn ($state) => "mailto:{$state}")
                            ->color('primary'),
                        IC\TextEntry::make('meta.customer_phone')
                            ->label('Telefon')
                            ->url(fn ($state) => $state ? "tel:{$state}" : null)
                            ->default('N/A'),
                    ]),

                IC\Section::make('Bilete comandate')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        IC\TextEntry::make('tickets_count')
                            ->label('Total bilete')
                            ->formatStateUsing(function ($record) {
                                $count = $record->tickets->count();
                                return $count . ' bilet' . ($count > 1 ? 'e' : '');
                            }),
                        IC\ViewEntry::make('tickets_list')
                            ->label('')
                            ->view('filament.tenant.resources.order-resource.tickets-list'),
                    ]),

                IC\Section::make('Beneficiari')
                    ->icon('heroicon-o-users')
                    ->visible(fn ($record) => !empty($record->meta['beneficiaries']))
                    ->schema([
                        IC\ViewEntry::make('beneficiaries_list')
                            ->label('')
                            ->view('filament.tenant.resources.order-resource.beneficiaries-list'),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled(),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

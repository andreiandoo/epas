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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Detalii comandă')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->schema([
                        SC\TextEntry::make('order_number')
                            ->label('Număr comandă')
                            ->formatStateUsing(fn ($record) => '#' . str_pad($record->id, 6, '0', STR_PAD_LEFT))
                            ->weight('bold')
                            ->size('lg'),
                        SC\TextEntry::make('status')
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
                        SC\TextEntry::make('total_cents')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . ($record->tickets->first()?->ticketType?->currency ?? 'RON'))
                            ->weight('bold')
                            ->size('lg'),
                        SC\TextEntry::make('created_at')
                            ->label('Data comenzii')
                            ->dateTime('d M Y H:i'),
                        SC\TextEntry::make('meta.payment_method')
                            ->label('Metodă plată')
                            ->default('Card'),
                        SC\TextEntry::make('updated_at')
                            ->label('Ultima actualizare')
                            ->dateTime('d M Y H:i'),
                    ]),

                SC\Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        SC\TextEntry::make('meta.customer_name')
                            ->label('Nume')
                            ->default('N/A'),
                        SC\TextEntry::make('customer_email')
                            ->label('Email')
                            ->url(fn ($state) => "mailto:{$state}")
                            ->color('primary'),
                        SC\TextEntry::make('meta.customer_phone')
                            ->label('Telefon')
                            ->url(fn ($state) => $state ? "tel:{$state}" : null)
                            ->default('N/A'),
                    ]),

                SC\Section::make('Bilete comandate')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        SC\TextEntry::make('tickets_count')
                            ->label('Total bilete')
                            ->formatStateUsing(function ($record) {
                                $count = $record->tickets->count();
                                return $count . ' bilet' . ($count > 1 ? 'e' : '');
                            }),
                        SC\ViewEntry::make('tickets_list')
                            ->label('')
                            ->view('filament.tenant.resources.order-resource.tickets-list'),
                    ]),

                SC\Section::make('Beneficiari')
                    ->icon('heroicon-o-users')
                    ->visible(fn ($record) => !empty($record->meta['beneficiaries']))
                    ->schema([
                        SC\ViewEntry::make('beneficiaries_list')
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
                Tables\Columns\TextColumn::make('id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('meta.customer_name')
                    ->label('Nume')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . ($record->tickets->first()?->ticketType?->currency ?? 'RON'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['confirmed', 'paid']),
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
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

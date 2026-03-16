<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceRefundRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use BackedEnum;
use UnitEnum;

class MarketplaceRefundRequestResource extends Resource
{
    protected static ?string $model = MarketplaceRefundRequest::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationLabel = 'Refund Requests';
    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 30;
    protected static ?string $modelLabel = 'Refund Request';
    protected static ?string $pluralModelLabel = 'Refund Requests';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['pending', 'under_review', 'approved'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference Number')
                            ->disabled(),

                        Forms\Components\Placeholder::make('marketplace')
                            ->label('Marketplace')
                            ->content(fn ($record) => $record?->marketplaceClient?->name ?? 'N/A'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'full_refund' => 'Full Refund',
                                'partial_refund' => 'Partial Refund',
                                'cancellation' => 'Cancellation',
                            ])
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'processing' => 'Processing',
                                'refunded' => 'Refunded',
                                'partially_refunded' => 'Partially Refunded',
                                'failed' => 'Failed',
                            ])
                            ->disabled(),
                    ])->columns(2),

                Section::make('Customer & Order')
                    ->schema([
                        Forms\Components\Placeholder::make('customer_info')
                            ->label('Customer')
                            ->content(fn ($record) => $record?->customer?->full_name . ' <' . $record?->customer?->email . '>'),

                        Forms\Components\Placeholder::make('order_info')
                            ->label('Order')
                            ->content(fn ($record) => '#' . str_pad($record?->order_id, 6, '0', STR_PAD_LEFT) . ' - ' . number_format($record?->order?->total, 2) . ' ' . ($record?->order?->currency ?? 'RON')),

                        Forms\Components\Placeholder::make('organizer_info')
                            ->label('Organizer')
                            ->content(fn ($record) => $record?->organizer?->name ?? 'N/A'),
                    ])->columns(3),

                Section::make('Amount')
                    ->schema([
                        Forms\Components\TextInput::make('requested_amount')
                            ->label('Requested Amount')
                            ->disabled()
                            ->prefix('RON'),

                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Approved Amount')
                            ->disabled()
                            ->prefix('RON'),
                    ])->columns(2),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('customer_notes')
                            ->label('Customer Notes')
                            ->disabled()
                            ->rows(3),

                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->disabled()
                            ->rows(3),
                    ])->columns(2),

                Section::make('Processing')
                    ->schema([
                        Forms\Components\Placeholder::make('processed_at')
                            ->label('Processed At')
                            ->content(fn ($record) => $record?->processed_at?->format('Y-m-d H:i') ?? 'Not processed'),

                        Forms\Components\Placeholder::make('refund_method')
                            ->label('Refund Method')
                            ->content(fn ($record) => ucfirst(str_replace('_', ' ', $record?->refund_method ?? 'N/A'))),
                    ])->columns(2)
                    ->visible(fn ($record) => $record?->processed_at),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'full_refund' => 'Full',
                        'partial_refund' => 'Partial',
                        'cancellation' => 'Cancel',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('requested_amount')
                    ->label('Amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'under_review' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'processing' => 'primary',
                        'refunded', 'partially_refunded' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processing' => 'Processing',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                        'failed' => 'Failed',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'full_refund' => 'Full Refund',
                        'partial_refund' => 'Partial Refund',
                        'cancellation' => 'Cancellation',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceRefundRequestResource\Pages\ListMarketplaceRefundRequests::route('/'),
            'view' => MarketplaceRefundRequestResource\Pages\ViewMarketplaceRefundRequest::route('/{record}'),
        ];
    }
}

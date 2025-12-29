<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\RefundRequestResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceRefundRequest;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class RefundRequestResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceRefundRequest::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Refund Requests';

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        $count = MarketplaceRefundRequest::where('marketplace_client_id', $marketplace?->id)
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->with(['customer', 'order', 'organizer']);
    }

    public static function canCreate(): bool
    {
        return false; // Refund requests are created by customers
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('request_number')
                            ->disabled(),
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
                        Forms\Components\TextInput::make('reason')
                            ->formatStateUsing(fn ($state) => MarketplaceRefundRequest::REASONS[$state] ?? $state)
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make('Customer & Order')
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

                SC\Section::make('Amount')
                    ->schema([
                        Forms\Components\TextInput::make('requested_amount')
                            ->label('Requested Amount')
                            ->disabled()
                            ->prefix('RON'),
                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Approved Amount')
                            ->numeric()
                            ->prefix('RON')
                            ->visible(fn ($record) => $record?->canBeProcessed()),
                    ])->columns(2),

                SC\Section::make('Customer Notes')
                    ->schema([
                        Forms\Components\Textarea::make('customer_notes')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes / Rejection Reason')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Processing Details')
                    ->schema([
                        Forms\Components\Select::make('refund_method')
                            ->options([
                                'original_payment' => 'Original Payment Method',
                                'bank_transfer' => 'Bank Transfer',
                                'store_credit' => 'Store Credit',
                                'manual' => 'Manual (External)',
                            ])
                            ->visible(fn ($record) => $record?->isApproved()),
                        Forms\Components\TextInput::make('refund_reference')
                            ->label('Reference Number')
                            ->visible(fn ($record) => $record?->isRefunded()),
                        Forms\Components\Placeholder::make('processed_info')
                            ->label('Processed')
                            ->content(fn ($record) => $record?->processed_at?->format('Y-m-d H:i') . ' by ' . ($record?->processedBy?->name ?? 'System'))
                            ->visible(fn ($record) => $record?->processed_at),
                    ])->columns(2)
                    ->visible(fn ($record) => in_array($record?->status, ['approved', 'processing', 'refunded', 'partially_refunded', 'failed'])),

                SC\Section::make('Auto-Refund Status')
                    ->schema([
                        Forms\Components\Placeholder::make('auto_refund_status')
                            ->content(function ($record) {
                                if (!$record?->auto_refund_attempted) {
                                    return 'Auto-refund not attempted';
                                }
                                if ($record?->auto_refund_error) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-red-600">Failed: ' . e($record->auto_refund_error) . '</span>');
                                }
                                return new \Illuminate\Support\HtmlString('<span class="text-green-600">Auto-refund successful</span>');
                            }),
                    ])
                    ->visible(fn ($record) => $record?->auto_refund_attempted)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(['customer.first_name', 'customer.last_name']),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order #')
                    ->formatStateUsing(fn ($state) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT)),
                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
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
                    ->color(fn ($record) => $record->status_color),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('review')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->action(fn ($record) => $record->markUnderReview()),

                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('approved_amount')
                                ->label('Approved Amount')
                                ->numeric()
                                ->required()
                                ->default(fn ($record) => $record->requested_amount)
                                ->prefix('RON'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Admin Notes'),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review']))
                        ->action(function ($record, array $data) {
                            $record->approve($data['approved_amount'], $data['notes']);
                            Notification::make()
                                ->title('Refund Approved')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Rejection Reason')
                                ->required(),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review']))
                        ->requiresConfirmation()
                        ->action(function ($record, array $data) {
                            $record->reject($data['reason']);
                            Notification::make()
                                ->title('Refund Rejected')
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\Action::make('process_auto')
                        ->label('Auto Refund')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'approved')
                        ->requiresConfirmation()
                        ->modalDescription('Attempt to process refund automatically through the payment provider.')
                        ->action(function ($record) {
                            $success = $record->attemptAutoRefund();
                            if ($success) {
                                Notification::make()
                                    ->title('Auto Refund Successful')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Auto Refund Failed')
                                    ->body($record->auto_refund_error)
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('process_manual')
                        ->label('Mark as Refunded')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('reference')
                                ->label('Reference Number')
                                ->helperText('External refund reference/transaction ID'),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['approved', 'failed']))
                        ->action(function ($record, array $data) {
                            $record->markRefunded($data['reference'], auth()->id());
                            Notification::make()
                                ->title('Marked as Refunded')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefundRequests::route('/'),
            'view' => Pages\ViewRefundRequest::route('/{record}'),
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\RefundRequestResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Support\MarketplaceTz;
use App\Jobs\SendRefundNotificationsJob;
use App\Models\MarketplaceRefundRequest;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class RefundRequestResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceRefundRequest::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Cereri retur';

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
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
                SC\Section::make(__('Request Details'))
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label(__('Reference Number'))
                            ->disabled(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'full_refund' => __('Full Refund'),
                                'partial_refund' => __('Partial Refund'),
                                'cancellation' => __('Cancellation'),
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => __('Pending'),
                                'under_review' => __('Under Review'),
                                'approved' => __('Approved'),
                                'rejected' => __('Rejected'),
                                'processing' => __('Processing'),
                                'refunded' => __('Refunded'),
                                'partially_refunded' => __('Partially Refunded'),
                                'failed' => __('Failed'),
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('reason')
                            ->formatStateUsing(fn ($state) => MarketplaceRefundRequest::REASONS[$state] ?? $state)
                            ->disabled(),
                    ])->columns(2),

                SC\Section::make(__('Customer & Order'))
                    ->schema([
                        Forms\Components\Placeholder::make('customer_info')
                            ->label(__('Customer'))
                            ->content(fn ($record) => $record?->customer?->full_name . ' <' . $record?->customer?->email . '>'),
                        Forms\Components\Placeholder::make('order_info')
                            ->label(__('Order'))
                            ->content(fn ($record) => '#' . str_pad($record?->order_id, 6, '0', STR_PAD_LEFT) . ' - ' . number_format($record?->order?->total, 2) . ' ' . ($record?->order?->currency ?? 'RON')),
                        Forms\Components\Placeholder::make('organizer_info')
                            ->label(__('Organizer'))
                            ->content(fn ($record) => $record?->organizer?->name ?? 'N/A'),
                    ])->columns(3),

                SC\Section::make(__('Amount'))
                    ->schema([
                        Forms\Components\TextInput::make('requested_amount')
                            ->label(__('Requested Amount'))
                            ->disabled()
                            ->prefix('RON'),
                        Forms\Components\TextInput::make('approved_amount')
                            ->label(__('Approved Amount'))
                            ->numeric()
                            ->prefix('RON')
                            ->visible(fn ($record) => $record?->canBeProcessed()),
                    ])->columns(2),

                SC\Section::make(__('Customer Notes'))
                    ->schema([
                        Forms\Components\Textarea::make('customer_notes')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make(__('Admin Notes'))
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label(__('Admin Notes / Rejection Reason'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                SC\Section::make(__('Processing Details'))
                    ->schema([
                        Forms\Components\Select::make('refund_method')
                            ->options([
                                'original_payment' => __('Original Payment Method'),
                                'bank_transfer' => __('Bank Transfer'),
                                'store_credit' => __('Store Credit'),
                                'manual' => __('Manual (External)'),
                            ])
                            ->visible(fn ($record) => $record?->isApproved()),
                        Forms\Components\TextInput::make('payment_refund_id')
                            ->label(__('Payment Reference'))
                            ->visible(fn ($record) => $record?->isRefunded()),
                        Forms\Components\Placeholder::make('processed_info')
                            ->label(__('Processed'))
                            ->content(fn ($record) => MarketplaceTz::fmt($record?->processed_at, 'Y-m-d H:i', $record?->marketplaceClient) . ' by ' . ($record?->processedBy?->name ?? 'System'))
                            ->visible(fn ($record) => $record?->processed_at),
                    ])->columns(2)
                    ->visible(fn ($record) => in_array($record?->status, ['approved', 'processing', 'refunded', 'partially_refunded', 'failed'])),

                SC\Section::make(__('Auto-Refund Status'))
                    ->schema([
                        Forms\Components\Placeholder::make('auto_refund_status')
                            ->content(function ($record) {
                                if (!$record?->is_automatic) {
                                    return 'Processed manually';
                                }
                                if ($record?->status === 'failed') {
                                    return new \Illuminate\Support\HtmlString('<span class="text-red-600">Auto-refund failed</span>');
                                }
                                return new \Illuminate\Support\HtmlString('<span class="text-green-600">Auto-refund successful</span>');
                            }),
                    ])
                    ->visible(fn ($record) => $record?->is_automatic || $record?->status === 'failed')
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable()
                    ->sortable(),
                // Customer with the order number and organizer as the muted line
                // (the Order # and Organizer columns stay in "Coloane").
                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label(__('Customer'))
                    ->description(fn ($record): string => implode(' | ', array_filter([
                        $record->order_id ? __('Comanda') . ' #' . str_pad((string) $record->order_id, 6, '0', STR_PAD_LEFT) : null,
                        $record->organizer?->name,
                    ])))
                    ->searchable(['customer.first_name', 'customer.last_name']),
                Tables\Columns\TextColumn::make('order_id')
                    ->label(__('Order #'))
                    ->formatStateUsing(fn ($state) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('organizer.name')
                    ->label(__('Organizer'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'full_refund' => 'Full',
                        'partial_refund' => 'Partial',
                        'cancellation' => 'Cancel',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                // Amount with the request type under it (Type stays in "Coloane").
                Tables\Columns\TextColumn::make('requested_amount')
                    ->label(__('Amount'))
                    ->money('RON')
                    ->description(fn ($record): ?string => match ($record->type) {
                        'full_refund' => __('Rambursare integrală'),
                        'partial_refund' => __('Rambursare parțială'),
                        'cancellation' => __('Anulare'),
                        default => null,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->html()
                    ->state(function ($record): HtmlString {
                        [$label, $tone] = match ($record->status) {
                            'pending' => [__('În așteptare'), 'warn'],
                            'under_review' => [__('În analiză'), 'warn'],
                            'approved' => [__('Aprobată'), 'ok'],
                            'rejected' => [__('Respinsă'), 'bad'],
                            'processing' => [__('În procesare'), 'warn'],
                            'refunded' => [__('Rambursată'), 'ok'],
                            'partially_refunded' => [__('Rambursată parțial'), 'warn'],
                            'failed' => [__('Eșuată'), 'bad'],
                            default => [ucfirst((string) $record->status), 'mute'],
                        };

                        return new HtmlString('<div class="ep-stare"><span class="ep-pill ep-pill--' . $tone . '"><span class="ep-dot"></span>' . e($label) . '</span></div>');
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Requested'))
                    ->dateTime(timezone: MarketplaceTz::tz())
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('Pending'),
                        'under_review' => __('Under Review'),
                        'approved' => __('Approved'),
                        'rejected' => __('Rejected'),
                        'processing' => __('Processing'),
                        'refunded' => __('Refunded'),
                        'partially_refunded' => __('Partially Refunded'),
                        'failed' => __('Failed'),
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'full_refund' => __('Full Refund'),
                        'partial_refund' => __('Partial Refund'),
                        'cancellation' => __('Cancellation'),
                    ]),
                Tables\Filters\SelectFilter::make('organizer')
                    ->relationship('organizer', 'name'),
            ])
            ->recordActions([
                // Next step as icon buttons (shown by status); Review + View in the menu.
                    Action::make('approve')
                        ->label(__('Approve'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->iconButton()
                        ->tooltip(__('Approve'))
                        ->form([
                            Forms\Components\TextInput::make('approved_amount')
                                ->label(__('Approved Amount'))
                                ->numeric()
                                ->required()
                                ->default(fn ($record) => $record->requested_amount)
                                ->prefix('RON'),
                            Forms\Components\Textarea::make('notes')
                                ->label(__('Admin Notes')),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review']))
                        ->action(function ($record, array $data) {
                            $record->approve($data['approved_amount'], $data['notes']);

                            // Send notification to customer
                            SendRefundNotificationsJob::dispatch($record, SendRefundNotificationsJob::TYPE_APPROVED);

                            Notification::make()
                                ->title(__('Refund Approved'))
                                ->success()
                                ->send();
                        }),

                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->iconButton()
                        ->tooltip(__('Reject'))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label(__('Rejection Reason'))
                                ->required(),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review']))
                        ->requiresConfirmation()
                        ->action(function ($record, array $data) {
                            $record->reject($data['reason']);

                            // Send notification to customer
                            SendRefundNotificationsJob::dispatch($record, SendRefundNotificationsJob::TYPE_REJECTED);

                            Notification::make()
                                ->title(__('Refund Rejected'))
                                ->warning()
                                ->send();
                        }),

                    Action::make('process_auto')
                        ->label(__('Auto Refund'))
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->iconButton()
                        ->tooltip(__('Auto Refund'))
                        ->visible(fn ($record) => $record->status === 'approved')
                        ->requiresConfirmation()
                        ->modalDescription(__('Attempt to process refund automatically through the payment provider.'))
                        ->action(function ($record) {
                            // Operator-started refund the processor refused: same path as the order page
                            $service = app(\App\Services\PaymentRefundService::class);
                            if ($service->isPendingManualRefund($record)) {
                                $result = $service->retryPendingRefund($record);
                                Notification::make()
                                    ->title($result->success ? 'Auto Refund Successful' : 'Auto Refund Failed')
                                    ->body($result->success ? null : $result->error)
                                    ->color($result->success ? 'success' : 'danger')
                                    ->send();
                                return;
                            }

                            $success = $record->attemptAutoRefund();
                            if ($success) {
                                // Send notification to customer
                                SendRefundNotificationsJob::dispatch($record->fresh(), SendRefundNotificationsJob::TYPE_COMPLETED);

                                Notification::make()
                                    ->title(__('Auto Refund Successful'))
                                    ->success()
                                    ->send();
                            } else {
                                $record->refresh();
                                Notification::make()
                                    ->title(__('Auto Refund Failed'))
                                    ->body($record->admin_notes ? 'Check admin notes for details' : 'Requires manual processing')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('process_manual')
                        ->label(__('Mark as Refunded'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->iconButton()
                        ->tooltip(__('Mark as Refunded'))
                        ->form([
                            Forms\Components\TextInput::make('reference')
                                ->label(__('Reference Number'))
                                ->helperText(__('External refund reference/transaction ID')),
                        ])
                        ->visible(fn ($record) => in_array($record->status, ['approved', 'failed']))
                        ->action(function ($record, array $data) {
                            $service = app(\App\Services\PaymentRefundService::class);
                            if ($service->isPendingManualRefund($record)) {
                                $service->completeManualRefund($record, 'manual', $data['reference'] ?: null, auth()->id());
                                Notification::make()
                                    ->title(__('Marked as Refunded'))
                                    ->success()
                                    ->send();
                                return;
                            }

                            $record->markRefunded($data['reference'], auth()->id());

                            // Send notification to customer
                            SendRefundNotificationsJob::dispatch($record, SendRefundNotificationsJob::TYPE_COMPLETED);

                            Notification::make()
                                ->title(__('Marked as Refunded'))
                                ->success()
                                ->send();
                        }),

                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('review')
                        ->label(__('Review'))
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->action(fn ($record) => $record->markUnderReview()),

                ])
                    ->label(__('Mai multe'))
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->iconButton(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefundRequests::route('/'),
            'view' => Pages\ViewRefundRequest::route('/{record}'),
        ];
    }
}

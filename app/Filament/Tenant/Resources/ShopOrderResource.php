<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShopOrderResource\Pages;
use App\Models\Shop\ShopOrder;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ShopOrderResource extends Resource
{
    protected static ?string $model = ShopOrder::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Shop Orders';

    public static function getNavigationLabel(): string
    {
        return __('Shop Orders');
    }

    protected static ?string $navigationParentItem = 'Shop';

    public static function getNavigationParentItem(): ?string
    {
        return __('Shop');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Services');
    }

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Order';

    public static function getModelLabel(): string
    {
        return __('Order');
    }

    protected static ?string $pluralModelLabel = 'Shop Orders';

    public static function getPluralModelLabel(): string
    {
        return __('Shop Orders');
    }

    protected static ?string $slug = 'shop-orders';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Order Details')
                    ->icon('heroicon-o-shopping-bag')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('order_number')
                            ->label(__('Order Number'))
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold">#' . $record->order_number . '</span>')),

                        Forms\Components\Placeholder::make('status')
                            ->label(__('Status'))
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'pending_payment' => 'bg-warning-100 text-warning-700',
                                'paid', 'confirmed' => 'bg-success-100 text-success-700',
                                'processing' => 'bg-primary-100 text-primary-700',
                                'shipped' => 'bg-info-100 text-info-700',
                                'delivered' => 'bg-success-100 text-success-700',
                                'cancelled', 'refunded' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst(str_replace('_', ' ', $record->status)) . '</span>')),

                        Forms\Components\Placeholder::make('payment_status')
                            ->label(__('Payment'))
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->payment_status) {
                                'paid' => 'bg-success-100 text-success-700',
                                'pending' => 'bg-warning-100 text-warning-700',
                                'failed' => 'bg-danger-100 text-danger-700',
                                'refunded' => 'bg-gray-100 text-gray-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst($record->payment_status) . '</span>')),

                        Forms\Components\Placeholder::make('fulfillment_status')
                            ->label(__('Fulfillment'))
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->fulfillment_status) {
                                'fulfilled' => 'bg-success-100 text-success-700',
                                'unfulfilled' => 'bg-warning-100 text-warning-700',
                                'partial' => 'bg-primary-100 text-primary-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst($record->fulfillment_status) . '</span>')),
                    ]),

                SC\Section::make('Customer')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('customer_email')
                            ->label('Email')
                            ->content(fn ($record) => new HtmlString('<a href="mailto:' . $record->customer_email . '" class="text-primary-600 hover:underline">' . $record->customer_email . '</a>')),

                        Forms\Components\Placeholder::make('billing_address')
                            ->label(__('Billing Address'))
                            ->content(function ($record) {
                                $billing = $record->billing_address ?? [];
                                if (empty($billing)) return 'N/A';
                                return new HtmlString(
                                    ($billing['name'] ?? '') . '<br>' .
                                    ($billing['street'] ?? '') . '<br>' .
                                    ($billing['city'] ?? '') . ', ' . ($billing['postal_code'] ?? '') . '<br>' .
                                    ($billing['country'] ?? '')
                                );
                            }),

                        Forms\Components\Placeholder::make('shipping_address')
                            ->label(__('Shipping Address'))
                            ->content(function ($record) {
                                $shipping = $record->shipping_address ?? [];
                                if (empty($shipping)) return 'Same as billing';
                                return new HtmlString(
                                    ($shipping['name'] ?? '') . '<br>' .
                                    ($shipping['street'] ?? '') . '<br>' .
                                    ($shipping['city'] ?? '') . ', ' . ($shipping['postal_code'] ?? '') . '<br>' .
                                    ($shipping['country'] ?? '')
                                );
                            }),
                    ]),

                SC\Section::make(__('Totals'))
                    ->icon('heroicon-o-calculator')
                    ->columns(5)
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal')
                            ->label('Subtotal')
                            ->content(fn ($record) => number_format($record->subtotal_cents / 100, 2) . ' ' . $record->currency),

                        Forms\Components\Placeholder::make('shipping')
                            ->label(__('Shipping'))
                            ->content(fn ($record) => number_format($record->shipping_cents / 100, 2) . ' ' . $record->currency),

                        Forms\Components\Placeholder::make('discount')
                            ->label('Discount')
                            ->content(fn ($record) => $record->discount_cents > 0
                                ? new HtmlString('<span class="text-success-600">-' . number_format($record->discount_cents / 100, 2) . ' ' . $record->currency . '</span>')
                                : '-'),

                        Forms\Components\Placeholder::make('tax')
                            ->label(__('Tax'))
                            ->content(fn ($record) => number_format($record->tax_cents / 100, 2) . ' ' . $record->currency),

                        Forms\Components\Placeholder::make('total')
                            ->label('Total')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold">' . number_format($record->total_cents / 100, 2) . ' ' . $record->currency . '</span>')),
                    ]),

                SC\Section::make(__('Order Items'))
                    ->icon('heroicon-o-cube')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('items_list')
                            ->label('')
                            ->content(function ($record) {
                                $items = $record->items;
                                if ($items->isEmpty()) {
                                    return 'No items';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($items as $item) {
                                    $html .= '<div class="flex justify-between items-center p-2 bg-gray-50 rounded">';
                                    $html .= '<div>';
                                    $html .= '<span class="font-medium">' . ($item->product?->title[app()->getLocale()] ?? $item->product_name ?? 'Unknown') . '</span>';
                                    if ($item->variant_name) {
                                        $html .= '<span class="text-gray-500 text-sm ml-2">(' . $item->variant_name . ')</span>';
                                    }
                                    $html .= '<br><span class="text-sm text-gray-600">Qty: ' . $item->quantity . ' × ' . number_format($item->unit_price_cents / 100, 2) . '</span>';
                                    $html .= '</div>';
                                    $html .= '<span class="font-medium">' . number_format($item->total_cents / 100, 2) . ' ' . $record->currency . '</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                SC\Section::make(__('Shipping'))
                    ->icon('heroicon-o-truck')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->shipping_method || $record->tracking_number)
                    ->schema([
                        Forms\Components\Placeholder::make('shipping_method')
                            ->label(__('Method'))
                            ->content(fn ($record) => $record->shipping_method ?? 'N/A'),

                        Forms\Components\Placeholder::make('tracking_number')
                            ->label(__('Tracking Number'))
                            ->content(fn ($record) => $record->tracking_number ?? 'N/A'),

                        Forms\Components\Placeholder::make('shipped_at')
                            ->label(__('Shipped At'))
                            ->content(fn ($record) => $record->shipped_at?->format('d M Y H:i') ?? 'Not shipped'),

                        Forms\Components\Placeholder::make('delivered_at')
                            ->label(__('Delivered At'))
                            ->content(fn ($record) => $record->delivered_at?->format('d M Y H:i') ?? 'Not delivered'),
                    ]),

                SC\Section::make(__('Timeline'))
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn ($record) => $record->created_at?->format('d M Y H:i')),

                        Forms\Components\Placeholder::make('paid_at')
                            ->label('Paid')
                            ->content(fn ($record) => $record->paid_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('shipped_at')
                            ->label(__('Shipped'))
                            ->content(fn ($record) => $record->shipped_at?->format('d M Y H:i') ?? '-'),

                        Forms\Components\Placeholder::make('delivered_at')
                            ->label(__('Delivered'))
                            ->content(fn ($record) => $record->delivered_at?->format('d M Y H:i') ?? '-'),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make(__('Update Order'))
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending_payment' => __('Pending Payment'),
                                'paid' => 'Paid',
                                'processing' => __('Processing'),
                                'shipped' => __('Shipped'),
                                'delivered' => __('Delivered'),
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),

                        Forms\Components\Select::make('fulfillment_status')
                            ->options([
                                'unfulfilled' => __('Unfulfilled'),
                                'partial' => __('Partially Fulfilled'),
                                'fulfilled' => __('Fulfilled'),
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('tracking_number')
                            ->label(__('Tracking Number'))
                            ->maxLength(100),

                        Forms\Components\TextInput::make('tracking_url')
                            ->label(__('Tracking URL'))
                            ->url()
                            ->maxLength(500),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label(__('Internal Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => fn ($state) => in_array($state, ['pending_payment']),
                        'success' => fn ($state) => in_array($state, ['paid', 'delivered']),
                        'primary' => fn ($state) => in_array($state, ['processing', 'shipped']),
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'refunded']),
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label(__('Payment'))
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),

                Tables\Columns\BadgeColumn::make('fulfillment_status')
                    ->label(__('Fulfillment'))
                    ->colors([
                        'success' => 'fulfilled',
                        'warning' => 'unfulfilled',
                        'primary' => 'partial',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_payment' => __('Pending Payment'),
                        'paid' => 'Paid',
                        'processing' => __('Processing'),
                        'shipped' => __('Shipped'),
                        'delivered' => __('Delivered'),
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => __('Failed'),
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('fulfillment_status')
                    ->options([
                        'unfulfilled' => __('Unfulfilled'),
                        'partial' => __('Partially Fulfilled'),
                        'fulfilled' => __('Fulfilled'),
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('From')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('Until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
                Actions\Action::make('mark_shipped')
                    ->label(__('Mark Shipped'))
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->status, ['paid', 'processing']) && $record->fulfillment_status !== 'fulfilled')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label(__('Tracking Number'))
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tracking_url')
                            ->label(__('Tracking URL'))
                            ->url(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'shipped',
                            'shipped_at' => now(),
                            'tracking_number' => $data['tracking_number'] ?? null,
                            'tracking_url' => $data['tracking_url'] ?? null,
                        ]);
                    }),
                Actions\Action::make('mark_delivered')
                    ->label(__('Mark Delivered'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'shipped')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'delivered',
                            'fulfillment_status' => 'fulfilled',
                            'delivered_at' => now(),
                        ]);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_processing')
                        ->label(__('Mark Processing'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'processing']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopOrders::route('/'),
            'view' => Pages\ViewShopOrder::route('/{record}'),
            'edit' => Pages\EditShopOrder::route('/{record}/edit'),
        ];
    }
}

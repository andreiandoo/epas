<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\GiftCardResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceGiftCard;
use App\Jobs\SendGiftCardEmailJob;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class GiftCardResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceGiftCard::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Carduri cadou';
    protected static ?string $modelLabel = 'Gift Card';
    protected static ?string $pluralModelLabel = 'Carduri cadou';

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        $count = MarketplaceGiftCard::where('marketplace_client_id', $marketplace?->id)
            ->where('status', MarketplaceGiftCard::STATUS_PENDING)
            ->where('is_delivered', false)
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
            ->with(['purchaser', 'recipient', 'purchaseOrder']);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->components([
                SC\Section::make('Gift Card Details')
                    ->schema([
                        Forms\Components\Hidden::make('marketplace_client_id')
                            ->default($marketplace?->id),

                        Forms\Components\TextInput::make('code')
                            ->label('Gift Card Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),

                        Forms\Components\Select::make('initial_amount')
                            ->label('Amount')
                            ->options(array_combine(
                                MarketplaceGiftCard::PRESET_AMOUNTS,
                                array_map(fn ($v) => number_format($v, 2) . ' RON', MarketplaceGiftCard::PRESET_AMOUNTS)
                            ))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set) => $set('balance', $state)),

                        Forms\Components\Hidden::make('balance'),

                        Forms\Components\Select::make('currency')
                            ->options(['RON' => 'RON', 'EUR' => 'EUR'])
                            ->default('RON')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                MarketplaceGiftCard::STATUS_PENDING => 'Pending',
                                MarketplaceGiftCard::STATUS_ACTIVE => 'Active',
                                MarketplaceGiftCard::STATUS_USED => 'Fully Used',
                                MarketplaceGiftCard::STATUS_EXPIRED => 'Expired',
                                MarketplaceGiftCard::STATUS_CANCELLED => 'Cancelled',
                                MarketplaceGiftCard::STATUS_REVOKED => 'Revoked',
                            ])
                            ->default(MarketplaceGiftCard::STATUS_PENDING)
                            ->required()
                            ->visible(fn ($record) => $record !== null),
                    ])->columns(2),

                SC\Section::make('Purchaser Information')
                    ->schema([
                        Forms\Components\TextInput::make('purchaser_email')
                            ->label('Purchaser Email')
                            ->email()
                            ->required(),

                        Forms\Components\TextInput::make('purchaser_name')
                            ->label('Purchaser Name'),

                        Forms\Components\Select::make('purchaser_id')
                            ->label('Link to Customer')
                            ->relationship('purchaser', 'email', fn (Builder $query) => $query->where('marketplace_client_id', static::getMarketplaceClientId()))
                            ->searchable()
                            ->preload(),
                    ])->columns(3),

                SC\Section::make('Recipient Information')
                    ->schema([
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->required(),

                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Recipient Name'),

                        Forms\Components\Select::make('occasion')
                            ->label('Occasion')
                            ->options(MarketplaceGiftCard::OCCASIONS),

                        Forms\Components\Textarea::make('personal_message')
                            ->label('Personal Message')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Delivery')
                    ->schema([
                        Forms\Components\Select::make('delivery_method')
                            ->options([
                                MarketplaceGiftCard::DELIVERY_EMAIL => 'Email',
                                MarketplaceGiftCard::DELIVERY_PRINT => 'Print at Home',
                            ])
                            ->default(MarketplaceGiftCard::DELIVERY_EMAIL)
                            ->required(),

                        Forms\Components\DateTimePicker::make('scheduled_delivery_at')
                            ->label('Schedule Delivery')
                            ->helperText('Leave empty to send immediately')
                            ->minDate(now()),

                        Forms\Components\Select::make('design_template')
                            ->label('Design Template')
                            ->options(fn () => \App\Models\MarketplaceGiftCardDesign::forMarketplace(static::getMarketplaceClientId())
                                ->active()
                                ->pluck('name', 'slug'))
                            ->default('default'),
                    ])->columns(3),

                SC\Section::make('Validity')
                    ->schema([
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->required()
                            ->default(now()->addYear())
                            ->minDate(now()),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Gift Card Details')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('code')
                            ->label('Code')
                            ->content(fn ($record) => new HtmlString('<span class="font-mono text-lg font-bold">' . $record->code . '</span>')),

                        Forms\Components\Placeholder::make('pin')
                            ->label('PIN')
                            ->content(fn ($record) => new HtmlString('<span class="font-mono">' . $record->pin . '</span>')),

                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'pending' => 'bg-warning-100 text-warning-700',
                                'active' => 'bg-success-100 text-success-700',
                                'used' => 'bg-info-100 text-info-700',
                                'expired' => 'bg-gray-100 text-gray-700',
                                'cancelled', 'revoked' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . $record->status_label . '</span>')),

                        Forms\Components\Placeholder::make('balance_display')
                            ->label('Balance')
                            ->content(fn ($record) => new HtmlString(
                                '<span class="text-lg font-bold">' . $record->formatted_balance . '</span>' .
                                ' / ' . number_format($record->initial_amount, 2) . ' ' . $record->currency .
                                ' <span class="text-sm text-gray-500">(' . $record->usage_percentage . '% used)</span>'
                            )),
                    ]),

                SC\Section::make('Purchaser')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('purchaser_name')
                            ->content(fn ($record) => $record->purchaser_name ?? 'N/A'),

                        Forms\Components\Placeholder::make('purchaser_email')
                            ->content(fn ($record) => $record->purchaser_email),

                        Forms\Components\Placeholder::make('purchase_order')
                            ->label('Order')
                            ->content(fn ($record) => $record->purchaseOrder
                                ? '#' . ($record->purchaseOrder->order_number ?? $record->purchase_order_id)
                                : 'Manual Creation'),
                    ]),

                SC\Section::make('Recipient')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('recipient_name')
                            ->content(fn ($record) => $record->recipient_name ?? 'N/A'),

                        Forms\Components\Placeholder::make('recipient_email')
                            ->content(fn ($record) => $record->recipient_email),

                        Forms\Components\Placeholder::make('occasion')
                            ->content(fn ($record) => $record->occasion_label ?? 'N/A'),

                        Forms\Components\Placeholder::make('personal_message')
                            ->label('Message')
                            ->content(fn ($record) => $record->personal_message ?? 'No message')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Delivery & Validity')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('delivery_method')
                            ->content(fn ($record) => ucfirst($record->delivery_method)),

                        Forms\Components\Placeholder::make('is_delivered')
                            ->label('Delivered')
                            ->content(fn ($record) => $record->is_delivered
                                ? 'Yes (' . $record->delivered_at?->format('d M Y H:i') . ')'
                                : ($record->scheduled_delivery_at ? 'Scheduled: ' . $record->scheduled_delivery_at->format('d M Y H:i') : 'Pending')),

                        Forms\Components\Placeholder::make('expires_at')
                            ->content(fn ($record) => $record->expires_at->format('d M Y') . ' (' . $record->days_until_expiry . ' days)'),

                        Forms\Components\Placeholder::make('claimed')
                            ->content(fn ($record) => $record->claimed_at
                                ? 'Yes (' . $record->claimed_at->format('d M Y H:i') . ')'
                                : 'Not yet'),
                    ]),

                SC\Section::make('Transaction History')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('transactions')
                            ->label('')
                            ->content(function ($record) {
                                $transactions = $record->transactions()->orderBy('created_at', 'desc')->get();

                                if ($transactions->isEmpty()) {
                                    return 'No transactions yet';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($transactions as $tx) {
                                    $color = $tx->isCredit() ? 'text-success-600' : 'text-danger-600';
                                    $html .= '<div class="flex justify-between items-center p-3 bg-gray-50 rounded">';
                                    $html .= '<div>';
                                    $html .= '<span class="font-medium">' . $tx->type_label . '</span>';
                                    $html .= '<span class="text-gray-500 text-sm ml-2">' . $tx->created_at->format('d M Y H:i') . '</span>';
                                    if ($tx->description) {
                                        $html .= '<br><span class="text-sm text-gray-600">' . e($tx->description) . '</span>';
                                    }
                                    $html .= '</div>';
                                    $html .= '<div class="text-right">';
                                    $html .= '<span class="font-medium ' . $color . '">' . $tx->formatted_amount . '</span>';
                                    $html .= '<br><span class="text-sm text-gray-500">Balance: ' . number_format($tx->balance_after, 2) . '</span>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('initial_amount')
                    ->label('Amount')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->money('RON')
                    ->sortable()
                    ->color(fn ($record) => $record->balance > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('occasion')
                    ->label('Occasion')
                    ->formatStateUsing(fn ($state) => MarketplaceGiftCard::OCCASIONS[$state] ?? $state)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_delivered')
                    ->label('Delivered')
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isExpiringSoon() ? 'warning' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        MarketplaceGiftCard::STATUS_PENDING => 'Pending',
                        MarketplaceGiftCard::STATUS_ACTIVE => 'Active',
                        MarketplaceGiftCard::STATUS_USED => 'Fully Used',
                        MarketplaceGiftCard::STATUS_EXPIRED => 'Expired',
                        MarketplaceGiftCard::STATUS_CANCELLED => 'Cancelled',
                        MarketplaceGiftCard::STATUS_REVOKED => 'Revoked',
                    ]),
                Tables\Filters\SelectFilter::make('occasion')
                    ->options(MarketplaceGiftCard::OCCASIONS),
                Tables\Filters\TernaryFilter::make('is_delivered')
                    ->label('Delivered'),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<=', now()->addDays(30))->where('expires_at', '>', now())),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('send_email')
                        ->label('Send/Resend Email')
                        ->icon('heroicon-o-envelope')
                        ->color('primary')
                        ->visible(fn ($record) => $record->delivery_method === 'email')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            SendGiftCardEmailJob::dispatch($record);
                            $record->markDelivered();

                            Notification::make()
                                ->title('Gift card email sent')
                                ->success()
                                ->send();
                        }),

                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->isPending())
                        ->action(function ($record) {
                            $record->activate();

                            Notification::make()
                                ->title('Gift card activated')
                                ->success()
                                ->send();
                        }),

                    Action::make('revoke')
                        ->label('Revoke')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->isActive())
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($record, array $data) {
                            $record->revoke($data['reason'], auth()->id());

                            Notification::make()
                                ->title('Gift card revoked')
                                ->warning()
                                ->send();
                        }),

                    Action::make('adjust_balance')
                        ->label('Adjust Balance')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->visible(fn ($record) => $record->isActive())
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount to Add/Subtract')
                                ->numeric()
                                ->required()
                                ->helperText('Use negative number to subtract'),
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $amount = (float) $data['amount'];

                            if ($amount > 0) {
                                $record->refund($amount, null, auth()->id(), $data['reason']);
                            } else {
                                $record->redeem(abs($amount));
                            }

                            Notification::make()
                                ->title('Balance adjusted')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftCards::route('/'),
            'create' => Pages\CreateGiftCard::route('/create'),
            'view' => Pages\ViewGiftCard::route('/{record}'),
            'edit' => Pages\EditGiftCard::route('/{record}/edit'),
        ];
    }
}

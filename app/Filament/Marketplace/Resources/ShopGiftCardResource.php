<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ShopGiftCardResource\Pages;
use App\Models\Shop\ShopGiftCard;
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
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class ShopGiftCardResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = ShopGiftCard::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Gift Cards';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Gift Card';

    protected static ?string $pluralModelLabel = 'Gift Cards';

    protected static ?string $slug = 'shop-gift-cards';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('shop');
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

                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'active' => 'bg-success-100 text-success-700',
                                'depleted' => 'bg-gray-100 text-gray-700',
                                'expired' => 'bg-warning-100 text-warning-700',
                                'disabled' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . ucfirst($record->status) . '</span>')),

                        Forms\Components\Placeholder::make('initial_balance_display')
                            ->label('Initial Balance')
                            ->content(fn ($record) => number_format($record->initial_balance, 2) . ' ' . $record->currency),

                        Forms\Components\Placeholder::make('current_balance_display')
                            ->label('Current Balance')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold">' . number_format($record->current_balance, 2) . ' ' . $record->currency . '</span>')),
                    ]),

                SC\Section::make('Purchaser')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('purchaser_email')
                            ->label('Email')
                            ->content(fn ($record) => $record->purchaser_email ?? 'N/A'),

                        Forms\Components\Placeholder::make('purchaser_customer')
                            ->label('Customer')
                            ->content(fn ($record) => $record->purchaserCustomer?->email ?? 'Guest'),

                        Forms\Components\Placeholder::make('purchase_order')
                            ->label('Order')
                            ->content(fn ($record) => $record->purchase_order_id ?? 'Manual'),
                    ]),

                SC\Section::make('Recipient')
                    ->columns(3)
                    ->visible(fn ($record) => !empty($record->recipient_email))
                    ->schema([
                        Forms\Components\Placeholder::make('recipient_name')
                            ->label('Name')
                            ->content(fn ($record) => $record->recipient_name ?? 'N/A'),

                        Forms\Components\Placeholder::make('recipient_email')
                            ->label('Email')
                            ->content(fn ($record) => $record->recipient_email),

                        Forms\Components\Placeholder::make('message')
                            ->label('Message')
                            ->content(fn ($record) => $record->message ?? 'No message'),

                        Forms\Components\Placeholder::make('sent_status')
                            ->label('Email Sent')
                            ->content(fn ($record) => $record->is_sent
                                ? 'Yes (' . $record->sent_at?->format('d M Y H:i') . ')'
                                : 'No'),
                    ]),

                SC\Section::make('Validity')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('valid_from')
                            ->label('Valid From')
                            ->content(fn ($record) => $record->valid_from?->format('d M Y H:i') ?? 'Immediately'),

                        Forms\Components\Placeholder::make('expires_at')
                            ->label('Expires At')
                            ->content(fn ($record) => $record->expires_at?->format('d M Y H:i') ?? 'Never'),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn ($record) => $record->created_at?->format('d M Y H:i')),
                    ]),

                SC\Section::make('Transaction History')
                    ->collapsible()
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
                                    $color = $tx->type === 'credit' ? 'text-success-600' : ($tx->type === 'refund' ? 'text-warning-600' : 'text-danger-600');
                                    $sign = $tx->type === 'credit' || $tx->type === 'refund' ? '+' : '-';
                                    $html .= '<div class="flex justify-between items-center p-2 bg-gray-50 rounded">';
                                    $html .= '<div>';
                                    $html .= '<span class="font-medium">' . ucfirst($tx->type) . '</span>';
                                    $html .= '<span class="text-gray-500 text-sm ml-2">' . $tx->created_at->format('d M Y H:i') . '</span>';
                                    if ($tx->description) {
                                        $html .= '<br><span class="text-sm text-gray-600">' . $tx->description . '</span>';
                                    }
                                    $html .= '</div>';
                                    $html .= '<span class="font-medium ' . $color . '">' . $sign . number_format($tx->amount_cents / 100, 2) . ' ' . $record->currency . '</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make('Gift Card Details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->default(fn () => strtoupper(Str::random(16)))
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('initial_balance')
                            ->label('Initial Balance')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(0.01)
                            ->prefix('RON')
                            ->live()
                            ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set) => $set('current_balance', $state)),

                        Forms\Components\Hidden::make('current_balance'),

                        Forms\Components\Select::make('currency')
                            ->options([
                                'RON' => 'RON',
                                'EUR' => 'EUR',
                                'USD' => 'USD',
                            ])
                            ->default('RON')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'depleted' => 'Depleted',
                                'expired' => 'Expired',
                                'disabled' => 'Disabled',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),

                SC\Section::make('Recipient (Optional)')
                    ->description('Send this gift card to someone')
                    ->schema([
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Recipient Name')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('message')
                            ->label('Personal Message')
                            ->rows(3)
                            ->maxLength(500),
                    ])->columns(2),

                SC\Section::make('Validity')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->placeholder('Immediately'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->placeholder('Never'),
                    ])->columns(2),
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
                    ->copyable(),

                Tables\Columns\TextColumn::make('initial_balance')
                    ->label('Initial')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->currency)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->currency)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('current_balance_cents', $direction)),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray' => 'depleted',
                        'warning' => 'expired',
                        'danger' => 'disabled',
                    ]),

                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(25),

                Tables\Columns\IconColumn::make('is_sent')
                    ->label('Sent')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'depleted' => 'Depleted',
                        'expired' => 'Expired',
                        'disabled' => 'Disabled',
                    ]),
                Tables\Filters\TernaryFilter::make('is_sent')
                    ->label('Email Sent'),
            ])
            ->recordActions([
                ViewAction::make(),
                Actions\Action::make('send_email')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->visible(fn ($record) => !$record->is_sent && !empty($record->recipient_email))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // TODO: Send email notification
                        $record->update([
                            'is_sent' => true,
                            'sent_at' => now(),
                        ]);
                    }),
                Actions\Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'disabled'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => Pages\ListShopGiftCards::route('/'),
            'create' => Pages\CreateShopGiftCard::route('/create'),
            'view' => Pages\ViewShopGiftCard::route('/{record}'),
            'edit' => Pages\EditShopGiftCard::route('/{record}/edit'),
        ];
    }
}

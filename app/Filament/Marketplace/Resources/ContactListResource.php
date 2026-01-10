<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ContactListResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEventCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ContactListResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceContactList::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Contact Lists';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        // Get categories for the marketplace
        $categories = MarketplaceEventCategory::where('marketplace_client_id', $marketplace?->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->getLocalizedName()])
            ->toArray();

        // Get unique cities from customers
        $cities = MarketplaceCustomer::where('marketplace_client_id', $marketplace?->id)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city', 'city')
            ->toArray();

        // Get unique states from customers
        $states = MarketplaceCustomer::where('marketplace_client_id', $marketplace?->id)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state', 'state')
            ->toArray();

        return $schema
            ->components([
                SC\Section::make('List Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\ToggleButtons::make('list_type')
                            ->label('List Type')
                            ->options([
                                'manual' => 'Manual',
                                'dynamic' => 'Dynamic (Rule-based)',
                            ])
                            ->icons([
                                'manual' => 'heroicon-o-user-plus',
                                'dynamic' => 'heroicon-o-cog-6-tooth',
                            ])
                            ->default('manual')
                            ->inline()
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Dynamic Rules Section
                SC\Section::make('Subscriber Rules')
                    ->description('Define conditions to automatically add subscribers to this list. All conditions must be met (AND logic).')
                    ->icon('heroicon-o-funnel')
                    ->schema([
                        Forms\Components\Repeater::make('rules')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Condition')
                                    ->options([
                                        'Newsletter' => [
                                            'newsletter_subscribed' => 'Subscribed to newsletter',
                                            'newsletter_unsubscribed' => 'Unsubscribed from newsletter',
                                        ],
                                        'Purchases' => [
                                            'has_purchases' => 'Has made at least one purchase',
                                            'purchase_count' => 'Number of purchases',
                                            'purchased_category' => 'Purchased from category',
                                        ],
                                        'Support' => [
                                            'has_refund_request' => 'Has requested refund',
                                        ],
                                        'Location' => [
                                            'city' => 'Lives in city',
                                            'state' => 'Lives in state/region',
                                        ],
                                        'Demographics' => [
                                            'age_less_than' => 'Age less than',
                                            'age_equals' => 'Age equals',
                                            'age_greater_than' => 'Age greater than',
                                        ],
                                    ])
                                    ->required()
                                    ->live()
                                    ->columnSpan(2),

                                // Operator for purchase count
                                Forms\Components\Select::make('operator')
                                    ->label('Operator')
                                    ->options([
                                        'equals' => 'Equals',
                                        'greater_than' => 'Greater than',
                                        'less_than' => 'Less than',
                                        'greater_or_equal' => 'Greater or equal',
                                        'less_or_equal' => 'Less or equal',
                                    ])
                                    ->default('greater_or_equal')
                                    ->visible(fn (SGet $get) => in_array($get('type'), ['purchase_count']))
                                    ->columnSpan(1),

                                // Numeric value for purchase count and age
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (SGet $get) => in_array($get('type'), [
                                        'purchase_count',
                                        'age_less_than',
                                        'age_equals',
                                        'age_greater_than',
                                    ]))
                                    ->columnSpan(1),

                                // Category selector
                                Forms\Components\Select::make('value')
                                    ->label('Category')
                                    ->options($categories)
                                    ->searchable()
                                    ->visible(fn (SGet $get) => $get('type') === 'purchased_category')
                                    ->columnSpan(2),

                                // City selector
                                Forms\Components\Select::make('value')
                                    ->label('City')
                                    ->options($cities)
                                    ->searchable()
                                    ->visible(fn (SGet $get) => $get('type') === 'city')
                                    ->columnSpan(2),

                                // State selector
                                Forms\Components\Select::make('value')
                                    ->label('State/Region')
                                    ->options($states)
                                    ->searchable()
                                    ->visible(fn (SGet $get) => $get('type') === 'state')
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add Condition')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(function (array $state): ?string {
                                $type = $state['type'] ?? null;
                                if (!$type) return 'New condition';

                                $labels = MarketplaceContactList::RULE_TYPES;
                                $label = $labels[$type] ?? $type;

                                if (isset($state['value']) && $state['value']) {
                                    if (in_array($type, ['purchase_count', 'age_less_than', 'age_equals', 'age_greater_than'])) {
                                        $operator = $state['operator'] ?? 'equals';
                                        $opSymbol = match($operator) {
                                            'equals' => '=',
                                            'greater_than' => '>',
                                            'less_than' => '<',
                                            'greater_or_equal' => '>=',
                                            'less_or_equal' => '<=',
                                            default => '',
                                        };
                                        return "{$label} {$opSymbol} {$state['value']}";
                                    }
                                    return "{$label}: {$state['value']}";
                                }

                                return $label;
                            })
                            ->defaultItems(0),

                        // Preview matching count
                        Forms\Components\Placeholder::make('matching_preview')
                            ->label('')
                            ->content(function ($record, SGet $get) {
                                if (!$record) {
                                    return new HtmlString('<div class="text-sm text-gray-500">Save the list to see matching subscribers count.</div>');
                                }

                                $rules = $get('rules') ?? [];
                                if (empty($rules)) {
                                    return new HtmlString('<div class="text-sm text-amber-600">No conditions defined. Add at least one condition.</div>');
                                }

                                // Temporarily update rules to get count
                                $tempList = clone $record;
                                $tempList->rules = $rules;
                                $tempList->list_type = 'dynamic';

                                try {
                                    $count = $tempList->buildMatchingCustomersQuery()->count();
                                    $color = $count > 0 ? 'text-green-600' : 'text-amber-600';
                                    return new HtmlString("<div class='text-sm {$color} font-medium'><span class='text-lg'>{$count}</span> customers match these conditions</div>");
                                } catch (\Exception $e) {
                                    return new HtmlString('<div class="text-sm text-red-600">Error evaluating rules. Please check your conditions.</div>');
                                }
                            }),

                        // Info about syncing
                        Forms\Components\Placeholder::make('sync_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="text-sm text-gray-500 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">' .
                                '<strong>How syncing works:</strong> Save the list to apply rules. ' .
                                'Use the "Sync Subscribers" button in the header to manually sync, ' .
                                'or subscribers will be synced automatically when customers are created/updated.' .
                                '</div>'
                            ))
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visible(fn (SGet $get) => $get('list_type') === 'dynamic')
                    ->collapsible(),

                // Manual Subscribers Section
                SC\Section::make('Subscribers')
                    ->schema([
                        Forms\Components\Placeholder::make('subscriber_count')
                            ->content(fn ($record) => $record ? $record->activeSubscribers()->count() . ' active subscribers' : '0 subscribers'),

                        Forms\Components\Select::make('add_subscribers')
                            ->label('Add Customers to List')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $marketplace = static::getMarketplaceClient();
                                return MarketplaceCustomer::where('marketplace_client_id', $marketplace?->id)
                                    ->where(function ($q) use ($search) {
                                        $q->where('email', 'like', "%{$search}%")
                                          ->orWhere('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name', 'like', "%{$search}%");
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->pluck('email', 'id');
                            })
                            ->getOptionLabelsUsing(function (array $values) {
                                return MarketplaceCustomer::whereIn('id', $values)
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => "{$c->full_name} <{$c->email}>"]);
                            })
                            ->helperText('Search and select customers to add to this list')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visible(fn (SGet $get, $record) => $get('list_type') === 'manual' && $record !== null),

                // Info for dynamic lists
                SC\Section::make('Subscriber Management')
                    ->schema([
                        Forms\Components\Placeholder::make('subscriber_info')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                '<div class="space-y-2">' .
                                '<div class="flex items-center gap-2">' .
                                '<span class="text-2xl font-bold text-primary-600">' . ($record ? $record->activeSubscribers()->count() : 0) . '</span>' .
                                '<span class="text-gray-600">active subscribers</span>' .
                                '</div>' .
                                ($record && $record->last_synced_at
                                    ? '<div class="text-sm text-gray-500">Last synced: ' . $record->last_synced_at->diffForHumans() . '</div>'
                                    : '<div class="text-sm text-amber-600">Not synced yet</div>') .
                                '</div>'
                            )),
                    ])
                    ->visible(fn (SGet $get, $record) => $get('list_type') === 'dynamic' && $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('list_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'dynamic' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subscribers_count')
                    ->counts('subscribers')
                    ->label('Subscribers'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('list_type')
                    ->label('Type')
                    ->options([
                        'manual' => 'Manual',
                        'dynamic' => 'Dynamic',
                    ]),
            ])
            ->recordActions([
                Action::make('sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->tooltip('Sync subscribers')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isDynamic())
                    ->action(function ($record) {
                        $added = $record->syncSubscribers();
                        \Filament\Notifications\Notification::make()
                            ->title('Sync Complete')
                            ->body("{$added} new subscribers added.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                Action::make('manage_subscribers')
                    ->icon('heroicon-o-users')
                    ->url(fn ($record) => static::getUrl('subscribers', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactLists::route('/'),
            'create' => Pages\CreateContactList::route('/create'),
            'edit' => Pages\EditContactList::route('/{record}/edit'),
            'subscribers' => Pages\ManageSubscribers::route('/{record}/subscribers'),
        ];
    }
}

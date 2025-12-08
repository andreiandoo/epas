<?php

namespace App\Filament\Resources;

use App\Models\Platform\PlatformAudience;
use App\Models\Platform\PlatformAdAccount;
use App\Services\Platform\PlatformTrackingService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PlatformAudienceResource extends Resource
{
    protected static ?string $model = PlatformAudience::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Audiences';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Audience';

    protected static ?string $pluralModelLabel = 'Audiences';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Audience Details')
                    ->description('Define your marketing audience')
                    ->schema([
                        Forms\Components\Select::make('platform_ad_account_id')
                            ->label('Ad Account')
                            ->options(PlatformAdAccount::active()->get()->mapWithKeys(fn ($account) => [
                                $account->id => $account->account_name . ' (' . (PlatformAdAccount::PLATFORMS[$account->platform] ?? $account->platform) . ')',
                            ]))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->label('Audience Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., High-Value Event Attendees'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Describe who this audience targets'),

                        Forms\Components\Select::make('audience_type')
                            ->label('Audience Type')
                            ->options(PlatformAudience::AUDIENCE_TYPES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('segment_rules', [])),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                PlatformAudience::STATUS_DRAFT => 'Draft',
                                PlatformAudience::STATUS_ACTIVE => 'Active',
                                PlatformAudience::STATUS_PAUSED => 'Paused',
                            ])
                            ->default(PlatformAudience::STATUS_DRAFT)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Sync Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_auto_sync')
                            ->label('Auto-Sync')
                            ->helperText('Automatically sync this audience to the ad platform')
                            ->default(false)
                            ->live(),

                        Forms\Components\Select::make('sync_frequency')
                            ->label('Sync Frequency')
                            ->options([
                                PlatformAudience::SYNC_HOURLY => 'Hourly',
                                PlatformAudience::SYNC_DAILY => 'Daily',
                                PlatformAudience::SYNC_WEEKLY => 'Weekly',
                                PlatformAudience::SYNC_MANUAL => 'Manual Only',
                            ])
                            ->default(PlatformAudience::SYNC_DAILY)
                            ->visible(fn ($get) => $get('is_auto_sync')),

                        Forms\Components\TextInput::make('platform_audience_id')
                            ->label('Platform Audience ID')
                            ->helperText('The ID of this audience in the ad platform (auto-populated after first sync)')
                            ->disabled()
                            ->visibleOn('edit'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Lookalike Configuration')
                    ->description('Configure the source and targeting for your lookalike audience')
                    ->schema([
                        Forms\Components\Select::make('lookalike_source_type')
                            ->label('Seed Source')
                            ->options(PlatformAudience::LOOKALIKE_SOURCE_TYPES)
                            ->required()
                            ->live()
                            ->helperText('Choose the customer segment to base your lookalike on'),

                        Forms\Components\Select::make('lookalike_source_audience_id')
                            ->label('Source Audience')
                            ->options(fn () => PlatformAudience::canBeSeed()
                                ->get()
                                ->mapWithKeys(fn ($aud) => [$aud->id => "{$aud->name} ({$aud->member_count} members)"]))
                            ->searchable()
                            ->visible(fn ($get) => $get('lookalike_source_type') === PlatformAudience::LOOKALIKE_SOURCE_AUDIENCE)
                            ->helperText('Select an existing audience to use as seed'),

                        Forms\Components\Select::make('lookalike_percentage')
                            ->label('Audience Size')
                            ->options(PlatformAudience::getLookalikePercentageOptions())
                            ->default(1)
                            ->required()
                            ->helperText('Lower percentages = more similar to seed, higher = broader reach'),

                        Forms\Components\Select::make('lookalike_country')
                            ->label('Target Country')
                            ->options(PlatformAudience::getLookalikeCountryOptions())
                            ->default('US')
                            ->required()
                            ->searchable()
                            ->helperText('The country where you want to find similar users'),

                        Forms\Components\Placeholder::make('lookalike_preview')
                            ->label('Estimated Reach')
                            ->content(function ($get, $record) {
                                if (!$record || !$record->isLookalike()) {
                                    $percentage = $get('lookalike_percentage') ?? 1;
                                    $country = $get('lookalike_country') ?? 'US';
                                    return "After saving, reach estimates will be shown for {$percentage}% lookalike in {$country}";
                                }
                                $estimate = $record->estimateLookalikeReach();
                                return view('filament.resources.platform-audience.lookalike-estimate', ['estimate' => $estimate]);
                            })
                            ->visibleOn('edit'),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('audience_type') === PlatformAudience::TYPE_LOOKALIKE),

                Forms\Components\Section::make('Custom Segment Rules')
                    ->description('Define custom rules for this audience')
                    ->schema([
                        Forms\Components\Repeater::make('segment_rules')
                            ->label('Rules')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label('Field')
                                    ->options([
                                        'total_orders' => 'Total Orders',
                                        'total_spent' => 'Total Spent ($)',
                                        'total_visits' => 'Total Visits',
                                        'engagement_score' => 'Engagement Score',
                                        'rfm_score' => 'RFM Score',
                                        'days_since_last_purchase' => 'Days Since Last Purchase',
                                        'customer_segment' => 'Customer Segment',
                                        'country_code' => 'Country',
                                        'first_utm_source' => 'First UTM Source',
                                        'last_utm_source' => 'Last UTM Source',
                                        'first_gclid' => 'Has Google Click ID',
                                        'first_fbclid' => 'Has Facebook Click ID',
                                        'email_subscribed' => 'Email Subscribed',
                                    ])
                                    ->required()
                                    ->searchable(),

                                Forms\Components\Select::make('operator')
                                    ->label('Operator')
                                    ->options([
                                        '=' => 'Equals',
                                        '!=' => 'Not Equals',
                                        '>' => 'Greater Than',
                                        '>=' => 'Greater or Equal',
                                        '<' => 'Less Than',
                                        '<=' => 'Less or Equal',
                                        'contains' => 'Contains',
                                        'is_null' => 'Is Empty',
                                        'is_not_null' => 'Is Not Empty',
                                        'days_ago' => 'Within Last X Days',
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->placeholder('Value to compare'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add Rule')
                            ->reorderable(false),
                    ])
                    ->visible(fn ($get) => $get('audience_type') === PlatformAudience::TYPE_CUSTOM)
                    ->collapsible(),

                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('member_count_display')
                            ->label('Members')
                            ->content(fn ($record) => number_format($record?->member_count ?? 0)),

                        Forms\Components\Placeholder::make('matched_count_display')
                            ->label('Matched')
                            ->content(fn ($record) => number_format($record?->matched_count ?? 0)),

                        Forms\Components\Placeholder::make('match_rate_display')
                            ->label('Match Rate')
                            ->content(fn ($record) => ($record?->getMatchRate() ?? 0) . '%'),

                        Forms\Components\Placeholder::make('last_synced_display')
                            ->label('Last Synced')
                            ->content(fn ($record) => $record?->last_synced_at?->diffForHumans() ?? 'Never'),
                    ])
                    ->columns(4)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->description(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('platformAdAccount.platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PlatformAdAccount::PLATFORMS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        PlatformAdAccount::PLATFORM_GOOGLE_ADS => 'danger',
                        PlatformAdAccount::PLATFORM_FACEBOOK => 'info',
                        PlatformAdAccount::PLATFORM_TIKTOK => 'warning',
                        PlatformAdAccount::PLATFORM_LINKEDIN => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('audience_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => PlatformAudience::AUDIENCE_TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn ($state) => $state === PlatformAudience::TYPE_LOOKALIKE ? 'info' : 'gray')
                    ->description(fn ($record) => $record->isLookalike()
                        ? "{$record->lookalike_percentage}% in {$record->lookalike_country}"
                        : null),

                Tables\Columns\TextColumn::make('member_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('matched_count')
                    ->label('Matched')
                    ->numeric()
                    ->description(fn ($record) => $record->getMatchRate() . '% rate'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        PlatformAudience::STATUS_ACTIVE => 'success',
                        PlatformAudience::STATUS_SYNCING => 'info',
                        PlatformAudience::STATUS_ERROR => 'danger',
                        PlatformAudience::STATUS_PAUSED => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_auto_sync')
                    ->label('Auto')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform_ad_account_id')
                    ->label('Ad Account')
                    ->options(PlatformAdAccount::active()->get()->mapWithKeys(fn ($account) => [
                        $account->id => $account->account_name ?? $account->account_id,
                    ])),

                Tables\Filters\SelectFilter::make('audience_type')
                    ->options(PlatformAudience::AUDIENCE_TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PlatformAudience::STATUS_DRAFT => 'Draft',
                        PlatformAudience::STATUS_ACTIVE => 'Active',
                        PlatformAudience::STATUS_SYNCING => 'Syncing',
                        PlatformAudience::STATUS_ERROR => 'Error',
                        PlatformAudience::STATUS_PAUSED => 'Paused',
                    ]),

                Tables\Filters\TernaryFilter::make('is_auto_sync')
                    ->label('Auto-Sync'),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $service = app(PlatformTrackingService::class);
                            $result = $service->syncAudience($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->success()
                                    ->title('Audience Synced')
                                    ->body("Synced {$result['member_count']} members ({$result['match_rate']}% match rate)")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Sync Failed')
                                    ->body($result['error'] ?? 'Unknown error')
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Sync Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Audience Preview')
                    ->modalContent(fn ($record) => view('filament.resources.platform-audience.preview', [
                        'audience' => $record,
                        'sampleCustomers' => $record->getCustomersQuery()->limit(10)->get(),
                        'totalCount' => $record->getCustomersQuery()->count(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('create_lookalike')
                    ->label('Create Lookalike')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn ($record) => !$record->isLookalike() && $record->member_count >= 100)
                    ->form([
                        Forms\Components\Select::make('platform_ad_account_id')
                            ->label('Ad Account')
                            ->options(PlatformAdAccount::active()->get()->mapWithKeys(fn ($account) => [
                                $account->id => $account->account_name . ' (' . (PlatformAdAccount::PLATFORMS[$account->platform] ?? $account->platform) . ')',
                            ]))
                            ->required(),
                        Forms\Components\Select::make('percentage')
                            ->label('Audience Size')
                            ->options(PlatformAudience::getLookalikePercentageOptions())
                            ->default(1)
                            ->required(),
                        Forms\Components\Select::make('country')
                            ->label('Target Country')
                            ->options(PlatformAudience::getLookalikeCountryOptions())
                            ->default('US')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('name')
                            ->label('Audience Name (optional)')
                            ->placeholder('Leave blank for auto-generated name'),
                    ])
                    ->action(function ($record, array $data) {
                        $account = PlatformAdAccount::find($data['platform_ad_account_id']);
                        $lookalike = $record->createLookalike(
                            $account,
                            $data['percentage'],
                            $data['country'],
                            $data['name'] ?: null
                        );

                        Notification::make()
                            ->success()
                            ->title('Lookalike Created')
                            ->body("Created lookalike audience: {$lookalike->name}")
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Audiences')
            ->emptyStateDescription('Create marketing audiences to sync customer segments to your ad platforms.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\PlatformAudienceResource\Pages\ListPlatformAudiences::route('/'),
            'create' => \App\Filament\Resources\PlatformAudienceResource\Pages\CreatePlatformAudience::route('/create'),
            'edit' => \App\Filament\Resources\PlatformAudienceResource\Pages\EditPlatformAudience::route('/{record}/edit'),
        ];
    }
}

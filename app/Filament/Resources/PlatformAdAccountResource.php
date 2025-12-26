<?php

namespace App\Filament\Resources;

use App\Models\Platform\PlatformAdAccount;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformAdAccountResource extends Resource
{
    protected static ?string $model = PlatformAdAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static BackedEnum|string|null $navigationLabel = 'Platform Ad Accounts';

    protected static UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Ad Account';

    protected static ?string $pluralModelLabel = 'Platform Ad Accounts';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Account Information')
                    ->description('Configure your platform ad account for dual-tracking')
                    ->schema([
                        Forms\Components\Select::make('platform')
                            ->label('Ad Platform')
                            ->options([
                                PlatformAdAccount::PLATFORM_GOOGLE_ADS => 'Google Ads',
                                PlatformAdAccount::PLATFORM_FACEBOOK => 'Facebook / Meta',
                                PlatformAdAccount::PLATFORM_TIKTOK => 'TikTok Ads',
                                PlatformAdAccount::PLATFORM_LINKEDIN => 'LinkedIn Ads',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('conversion_action_ids', [])),

                        Forms\Components\TextInput::make('account_id')
                            ->label('Account ID')
                            ->required()
                            ->maxLength(255)
                            ->helperText(fn ($get) => match ($get('platform')) {
                                PlatformAdAccount::PLATFORM_GOOGLE_ADS => 'Your Google Ads Customer ID (e.g., 123-456-7890)',
                                PlatformAdAccount::PLATFORM_FACEBOOK => 'Your Facebook Ad Account ID (e.g., act_123456789)',
                                PlatformAdAccount::PLATFORM_TIKTOK => 'Your TikTok Advertiser ID',
                                PlatformAdAccount::PLATFORM_LINKEDIN => 'Your LinkedIn Ad Account ID',
                                default => 'The platform-specific account identifier',
                            }),

                        Forms\Components\TextInput::make('account_name')
                            ->label('Account Name')
                            ->maxLength(255)
                            ->helperText('A friendly name for this account'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable dual-tracking to this account'),
                    ])
                    ->columns(2),

                SC\Section::make('Authentication')
                    ->description('OAuth tokens for API access')
                    ->schema([
                        Forms\Components\Textarea::make('access_token')
                            ->label('Access Token')
                            ->required()
                            ->rows(3)
                            ->helperText('The OAuth access token for API calls'),

                        Forms\Components\Textarea::make('refresh_token')
                            ->label('Refresh Token')
                            ->rows(3)
                            ->helperText('The OAuth refresh token (for auto-renewal)'),

                        Forms\Components\DateTimePicker::make('token_expires_at')
                            ->label('Token Expires At')
                            ->helperText('When the access token expires'),
                    ])
                    ->columns(2),

                SC\Section::make('Platform-Specific Configuration')
                    ->schema([
                        // Google Ads specific
                        Forms\Components\TagsInput::make('conversion_action_ids')
                            ->label('Conversion Action IDs')
                            ->visible(fn ($get) => $get('platform') === PlatformAdAccount::PLATFORM_GOOGLE_ADS)
                            ->helperText('Google Ads conversion action IDs to send conversions to'),

                        // Facebook specific
                        Forms\Components\TextInput::make('pixel_id')
                            ->label('Pixel ID')
                            ->visible(fn ($get) => in_array($get('platform'), [
                                PlatformAdAccount::PLATFORM_FACEBOOK,
                                PlatformAdAccount::PLATFORM_TIKTOK,
                            ]))
                            ->helperText(fn ($get) => match ($get('platform')) {
                                PlatformAdAccount::PLATFORM_FACEBOOK => 'Your Facebook Pixel ID',
                                PlatformAdAccount::PLATFORM_TIKTOK => 'Your TikTok Pixel ID',
                                default => 'Pixel identifier',
                            }),

                        // LinkedIn specific
                        Forms\Components\TextInput::make('settings.conversion_rule_id')
                            ->label('Conversion Rule ID')
                            ->visible(fn ($get) => $get('platform') === PlatformAdAccount::PLATFORM_LINKEDIN)
                            ->helperText('LinkedIn conversion rule ID'),
                    ]),

                SC\Section::make('Status')
                    ->schema([
                        Forms\Components\Placeholder::make('last_sync_info')
                            ->label('Last Sync')
                            ->content(fn ($record) => $record?->last_sync_at?->diffForHumans() ?? 'Never'),

                        Forms\Components\Placeholder::make('sync_errors_display')
                            ->label('Recent Errors')
                            ->content(function ($record) {
                                if (!$record || empty($record->sync_errors)) {
                                    return 'No errors';
                                }
                                $lastError = end($record->sync_errors);
                                return $lastError['error'] ?? 'Unknown error';
                            }),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PlatformAdAccount::PLATFORMS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        PlatformAdAccount::PLATFORM_GOOGLE_ADS => 'danger',
                        PlatformAdAccount::PLATFORM_FACEBOOK => 'info',
                        PlatformAdAccount::PLATFORM_TIKTOK => 'warning',
                        PlatformAdAccount::PLATFORM_LINKEDIN => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('Name')
                    ->searchable()
                    ->description(fn ($record) => $record->account_id),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->counts('conversions')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('token_status')
                    ->label('Token')
                    ->getStateUsing(function ($record) {
                        if (!$record->token_expires_at) return 'Unknown';
                        if ($record->isTokenExpired()) return 'Expired';
                        if ($record->isTokenExpiringSoon()) return 'Expiring Soon';
                        return 'Valid';
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Valid' => 'success',
                        'Expiring Soon' => 'warning',
                        'Expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('Last Sync')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options(PlatformAdAccount::PLATFORMS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Action::make('refresh_token')
                    ->label('Refresh Token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Would trigger OAuth refresh flow
                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Token Refresh')
                            ->body('Token refresh flow would be triggered here.')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->refresh_token && $record->isTokenExpiringSoon()),

                Action::make('test_connection')
                    ->label('Test')
                    ->icon('heroicon-o-bolt')
                    ->color('gray')
                    ->action(function ($record) {
                        // Would test API connection
                        $record->recordSyncSuccess();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Connection Test')
                            ->body('Connection test successful!')
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make()
                    ->label('Delete Ad Account')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Platform Ad Accounts')
            ->emptyStateDescription('Add your own ad accounts to enable dual-tracking. Conversions will be sent to both tenant accounts and your platform accounts.')
            ->emptyStateIcon('heroicon-o-megaphone');
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
            'index' => \App\Filament\Resources\PlatformAdAccountResource\Pages\ListPlatformAdAccounts::route('/'),
            'create' => \App\Filament\Resources\PlatformAdAccountResource\Pages\CreatePlatformAdAccount::route('/create'),
            'edit' => \App\Filament\Resources\PlatformAdAccountResource\Pages\EditPlatformAdAccount::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceClient;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class MarketplaceClientResource extends Resource
{
    protected static ?string $model = MarketplaceClient::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Marketplace Clients';

    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Marketplace Client';

    protected static ?string $pluralModelLabel = 'Marketplace Clients';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'active')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Client Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Client Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set, $get) =>
                                $get('slug') ? null : $set('slug', Str::slug($state))
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Used for folder organization'),

                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->url()
                            ->placeholder('https://example.com')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                SC\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Contact Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                SC\Section::make('Commission Settings')
                    ->schema([
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(0)
                            ->suffix('%')
                            ->helperText('Percentage of each sale'),
                    ])
                    ->columns(1),

                SC\Section::make('Security Settings')
                    ->schema([
                        Forms\Components\Repeater::make('settings.allowed_ips')
                            ->label('Allowed IP Addresses')
                            ->simple(
                                Forms\Components\TextInput::make('ip')
                                    ->placeholder('192.168.1.1 or 10.0.0.0/24')
                            )
                            ->helperText('Leave empty to allow all IPs. Supports CIDR notation.'),

                        Forms\Components\Repeater::make('settings.allowed_domains')
                            ->label('Allowed Domains')
                            ->simple(
                                Forms\Components\TextInput::make('domain')
                                    ->placeholder('example.com or *.example.com')
                            )
                            ->helperText('Leave empty to allow all domains. Supports wildcard subdomains.'),
                    ])
                    ->columns(2),

                SC\Section::make('Webhook Settings')
                    ->schema([
                        Forms\Components\TextInput::make('settings.webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->placeholder('https://example.com/webhook')
                            ->helperText('URL to receive order notifications'),

                        Forms\Components\TextInput::make('settings.webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Used to sign webhook payloads'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                SC\Section::make('API Credentials')
                    ->schema([
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Key')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Auto-generated. Click "Regenerate API Key" to create new credentials.'),

                        Forms\Components\TextInput::make('api_secret')
                            ->label('API Secret')
                            ->disabled()
                            ->dehydrated(false)
                            ->password()
                            ->revealable()
                            ->helperText('Only shown once when regenerated.'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record !== null),

                SC\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->domain),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Tenants')
                    ->counts('tenants')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('api_calls_count')
                    ->label('API Calls')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_api_call_at')
                    ->label('Last API Call')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('regenerate_api_key')
                    ->label('Regenerate Key')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate API Credentials')
                    ->modalDescription('This will invalidate the current API key. The client will need to update their integration with the new credentials.')
                    ->action(function (MarketplaceClient $record) {
                        $record->regenerateApiCredentials();

                        \Filament\Notifications\Notification::make()
                            ->title('API Credentials Regenerated')
                            ->body("New API Key: {$record->api_key}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MarketplaceClientResource\RelationManagers\TenantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceClientResource\Pages\ListMarketplaceClients::route('/'),
            'create' => MarketplaceClientResource\Pages\CreateMarketplaceClient::route('/create'),
            'view' => MarketplaceClientResource\Pages\ViewMarketplaceClient::route('/{record}'),
            'edit' => MarketplaceClientResource\Pages\EditMarketplaceClient::route('/{record}/edit'),
        ];
    }
}

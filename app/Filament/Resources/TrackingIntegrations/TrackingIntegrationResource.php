<?php

namespace App\Filament\Resources\TrackingIntegrations;

use App\Models\TrackingIntegration;
use App\Services\Tracking\Providers\TrackingProviderFactory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use BackedEnum;

class TrackingIntegrationResource extends Resource
{
    protected static ?string $model = TrackingIntegration::class;

    protected static UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static BackedEnum|string|null $navigationLabel = 'Tracking & Pixels';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $providers = TrackingProviderFactory::getAvailableProviders();

        return $schema->schema([
            SC\Section::make('Provider Configuration')
                ->description('Configure your tracking and pixel integrations')
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant')
                        ->relationship('tenant', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Select the tenant for this integration'),

                    Forms\Components\Select::make('provider')
                        ->label('Tracking Provider')
                        ->options([
                            'ga4' => 'Google Analytics 4',
                            'gtm' => 'Google Tag Manager',
                            'meta' => 'Meta Pixel (Facebook)',
                            'tiktok' => 'TikTok Pixel',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) use ($providers) {
                            if ($state && isset($providers[$state])) {
                                $set('consent_category', $providers[$state]['consent_category']);
                            }
                        })
                        ->helperText('Choose your tracking provider'),

                    Forms\Components\Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(false)
                        ->helperText('Enable or disable this integration'),

                    Forms\Components\Select::make('consent_category')
                        ->label('Consent Category')
                        ->options([
                            'analytics' => 'Analytics',
                            'marketing' => 'Marketing',
                        ])
                        ->required()
                        ->helperText('GDPR consent category required for this tracker'),
                ])->columns(2),

            SC\Section::make('Google Analytics 4 Settings')
                ->description('Configure your GA4 tracking')
                ->schema([
                    Forms\Components\TextInput::make('settings.measurement_id')
                        ->label('Measurement ID')
                        ->placeholder('G-XXXXXXXXXX')
                        ->required()
                        ->helperText('Your GA4 Measurement ID (found in GA4 Admin → Data Streams)'),

                    Forms\Components\Select::make('settings.inject_at')
                        ->label('Inject Script At')
                        ->options([
                            'head' => 'Head (recommended)',
                            'body' => 'Body End',
                        ])
                        ->default('head')
                        ->required(),

                    Forms\Components\Select::make('settings.page_scope')
                        ->label('Page Scope')
                        ->options([
                            'public' => 'Public Pages Only',
                            'admin' => 'Admin Pages Only',
                            'all' => 'All Pages',
                        ])
                        ->default('public')
                        ->required()
                        ->helperText('Where to inject tracking scripts'),
                ])
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('provider') === 'ga4')
                ->columns(3),

            SC\Section::make('Google Tag Manager Settings')
                ->description('Configure your GTM container')
                ->schema([
                    Forms\Components\TextInput::make('settings.container_id')
                        ->label('Container ID')
                        ->placeholder('GTM-XXXXXX')
                        ->required()
                        ->helperText('Your GTM Container ID (found in GTM Admin → Container Settings)'),

                    Forms\Components\Select::make('settings.inject_at')
                        ->label('Inject Script At')
                        ->options([
                            'head' => 'Head (recommended)',
                            'body' => 'Body End',
                        ])
                        ->default('head')
                        ->required(),

                    Forms\Components\Select::make('settings.page_scope')
                        ->label('Page Scope')
                        ->options([
                            'public' => 'Public Pages Only',
                            'admin' => 'Admin Pages Only',
                            'all' => 'All Pages',
                        ])
                        ->default('public')
                        ->required()
                        ->helperText('Where to inject tracking scripts'),
                ])
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('provider') === 'gtm')
                ->columns(3),

            SC\Section::make('Meta Pixel Settings')
                ->description('Configure your Facebook Pixel')
                ->schema([
                    Forms\Components\TextInput::make('settings.pixel_id')
                        ->label('Pixel ID')
                        ->placeholder('1234567890123456')
                        ->required()
                        ->helperText('Your Meta Pixel ID (found in Facebook Events Manager)'),

                    Forms\Components\Select::make('settings.inject_at')
                        ->label('Inject Script At')
                        ->options([
                            'head' => 'Head (recommended)',
                            'body' => 'Body End',
                        ])
                        ->default('head')
                        ->required(),

                    Forms\Components\Select::make('settings.page_scope')
                        ->label('Page Scope')
                        ->options([
                            'public' => 'Public Pages Only',
                            'admin' => 'Admin Pages Only',
                            'all' => 'All Pages',
                        ])
                        ->default('public')
                        ->required()
                        ->helperText('Where to inject tracking scripts'),
                ])
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('provider') === 'meta')
                ->columns(3),

            SC\Section::make('TikTok Pixel Settings')
                ->description('Configure your TikTok Pixel')
                ->schema([
                    Forms\Components\TextInput::make('settings.pixel_id')
                        ->label('Pixel ID')
                        ->placeholder('C12ABC34DEF56GHI7JKL')
                        ->required()
                        ->helperText('Your TikTok Pixel ID (found in TikTok Ads Manager → Assets → Events)'),

                    Forms\Components\Select::make('settings.inject_at')
                        ->label('Inject Script At')
                        ->options([
                            'head' => 'Head (recommended)',
                            'body' => 'Body End',
                        ])
                        ->default('head')
                        ->required(),

                    Forms\Components\Select::make('settings.page_scope')
                        ->label('Page Scope')
                        ->options([
                            'public' => 'Public Pages Only',
                            'admin' => 'Admin Pages Only',
                            'all' => 'All Pages',
                        ])
                        ->default('public')
                        ->required()
                        ->helperText('Where to inject tracking scripts'),
                ])
                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('provider') === 'tiktok')
                ->columns(3),

            SC\Section::make('Important Notes')
                ->description('GDPR compliance and best practices')
                ->schema([
                    Forms\Components\Placeholder::make('gdpr_notice')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString('
                            <div class="space-y-2 text-sm">
                                <p><strong>🔒 GDPR Compliance:</strong> Scripts will only load if user has granted consent for the selected category.</p>
                                <p><strong>🍪 Consent Required:</strong> No tracking occurs without explicit user consent.</p>
                                <p><strong>📊 Analytics Category:</strong> Used for GA4 and GTM (website analytics).</p>
                                <p><strong>📢 Marketing Category:</strong> Used for Meta and TikTok (advertising pixels).</p>
                                <p><strong>⚡ Page Scope:</strong> Control where tracking scripts are injected (public/admin/all pages).</p>
                            </div>
                        ')),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\BadgeColumn::make('provider')
                    ->label('Provider')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ga4' => 'GA4',
                        'gtm' => 'GTM',
                        'meta' => 'Meta Pixel',
                        'tiktok' => 'TikTok',
                        default => $state,
                    })
                    ->colors([
                        'primary' => fn ($state) => in_array($state, ['ga4', 'gtm']),
                        'success' => 'meta',
                        'warning' => 'tiktok',
                    ]),

                Tables\Columns\IconColumn::make('enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\BadgeColumn::make('consent_category')
                    ->label('Consent')
                    ->colors([
                        'info' => 'analytics',
                        'warning' => 'marketing',
                    ]),

                Tables\Columns\TextColumn::make('provider_id')
                    ->label('ID')
                    ->getStateUsing(fn ($record) => $record->getProviderId())
                    ->limit(20),

                Tables\Columns\TextColumn::make('page_scope')
                    ->label('Scope')
                    ->getStateUsing(fn ($record) => $record->getPageScope())
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'ga4' => 'Google Analytics 4',
                        'gtm' => 'Google Tag Manager',
                        'meta' => 'Meta Pixel',
                        'tiktok' => 'TikTok Pixel',
                    ]),
                Tables\Filters\SelectFilter::make('enabled')
                    ->options([
                        1 => 'Enabled',
                        0 => 'Disabled',
                    ]),
                Tables\Filters\SelectFilter::make('consent_category')
                    ->options([
                        'analytics' => 'Analytics',
                        'marketing' => 'Marketing',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrackingIntegrations::route('/'),
            'create' => Pages\CreateTrackingIntegration::route('/create'),
            'edit' => Pages\EditTrackingIntegration::route('/{record}/edit'),
        ];
    }
}

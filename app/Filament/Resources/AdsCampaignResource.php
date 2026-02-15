<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdsCampaignResource\Pages;
use App\Filament\Resources\AdsCampaignResource\RelationManagers;
use App\Models\AdsCampaign\AdsCampaign;
use App\Models\Event;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class AdsCampaignResource extends Resource
{
    protected static ?string $model = AdsCampaign::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Ad Campaigns';

    protected static \UnitEnum|string|null $navigationGroup = 'Ads Manager';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Ad Campaign';

    protected static ?string $pluralModelLabel = 'Ad Campaigns';

    protected static ?string $slug = 'ads-campaigns';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ==========================================
                // CAMPAIGN BASICS
                // ==========================================
                SC\Section::make('Campaign Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Summer Festival 2026 - Launch Campaign'),

                        Forms\Components\Textarea::make('description')
                            ->label('Internal Notes')
                            ->rows(2)
                            ->placeholder('Internal notes about this campaign'),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Organizer (Tenant)')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(function (Forms\Get $get) {
                                $tenantId = $get('tenant_id');
                                if (!$tenantId) return [];
                                return Event::where('tenant_id', $tenantId)
                                    ->where('is_cancelled', false)
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->getTranslation('title', 'en') ?? $e->getTranslation('title', 'ro') ?? "Event #{$e->id}"]);
                            })
                            ->searchable()
                            ->reactive(),

                        Forms\Components\Select::make('service_request_id')
                            ->label('Service Request')
                            ->relationship('serviceRequest', 'name')
                            ->searchable()
                            ->placeholder('Link to organizer request (optional)'),
                    ])->columns(2),

                // ==========================================
                // OBJECTIVE & PLATFORMS
                // ==========================================
                SC\Section::make('Objective & Platforms')
                    ->schema([
                        Forms\Components\Select::make('objective')
                            ->label('Campaign Objective')
                            ->options([
                                'conversions' => '🎫 Ticket Sales (Conversions)',
                                'traffic' => '🔗 Website Traffic',
                                'awareness' => '📣 Brand Awareness',
                                'engagement' => '💬 Engagement',
                                'leads' => '📋 Lead Generation',
                            ])
                            ->default('conversions')
                            ->required()
                            ->helperText('Choose what you want this campaign to achieve'),

                        Forms\Components\CheckboxList::make('target_platforms')
                            ->label('Target Platforms')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'google' => 'Google Ads',
                            ])
                            ->default(['facebook', 'instagram'])
                            ->required()
                            ->columns(3),

                        Forms\Components\Select::make('optimization_goal')
                            ->label('Optimization Goal')
                            ->options([
                                'conversions' => 'Maximize Conversions',
                                'clicks' => 'Maximize Clicks',
                                'impressions' => 'Maximize Impressions',
                                'roas' => 'Target ROAS',
                            ])
                            ->default('conversions'),
                    ])->columns(2),

                // ==========================================
                // BUDGET & SCHEDULE
                // ==========================================
                SC\Section::make('Budget & Schedule')
                    ->schema([
                        Forms\Components\TextInput::make('total_budget')
                            ->label('Total Budget')
                            ->numeric()
                            ->required()
                            ->prefix('EUR')
                            ->minValue(10)
                            ->step(0.01),

                        Forms\Components\TextInput::make('daily_budget')
                            ->label('Daily Budget (optional)')
                            ->numeric()
                            ->prefix('EUR')
                            ->step(0.01)
                            ->helperText('Leave empty for lifetime budget pacing'),

                        Forms\Components\Select::make('budget_allocation')
                            ->label('Budget Allocation')
                            ->options([
                                'equal' => 'Equal across platforms',
                                'performance' => 'Auto-optimize by performance',
                                'manual' => 'Manual allocation',
                            ])
                            ->default('performance'),

                        Forms\Components\Select::make('currency')
                            ->options(['EUR' => 'EUR', 'RON' => 'RON', 'USD' => 'USD', 'GBP' => 'GBP'])
                            ->default('EUR'),

                        Forms\Components\DateTimePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),

                        Forms\Components\DateTimePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->after('start_date'),
                    ])->columns(3),

                // ==========================================
                // A/B TESTING
                // ==========================================
                SC\Section::make('A/B Testing')
                    ->schema([
                        Forms\Components\Toggle::make('ab_testing_enabled')
                            ->label('Enable A/B Testing')
                            ->reactive()
                            ->helperText('Test different creatives, audiences, or placements to find the best performer'),

                        Forms\Components\Select::make('ab_test_variable')
                            ->label('Test Variable')
                            ->options([
                                'creative' => 'Different Creatives',
                                'audience' => 'Different Audiences',
                                'placement' => 'Different Placements',
                                'copy' => 'Different Ad Copy',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('ab_testing_enabled')),

                        Forms\Components\TextInput::make('ab_test_split_percentage')
                            ->label('Traffic Split (% for Variant A)')
                            ->numeric()
                            ->default(50)
                            ->suffix('%')
                            ->minValue(20)
                            ->maxValue(80)
                            ->visible(fn (Forms\Get $get) => $get('ab_testing_enabled')),

                        Forms\Components\Select::make('ab_test_metric')
                            ->label('Winning Metric')
                            ->options([
                                'ctr' => 'Click-Through Rate (CTR)',
                                'conversions' => 'Total Conversions',
                                'cpc' => 'Cost per Click (lowest)',
                                'roas' => 'Return on Ad Spend (ROAS)',
                            ])
                            ->default('conversions')
                            ->visible(fn (Forms\Get $get) => $get('ab_testing_enabled')),
                    ])->columns(2)->collapsible(),

                // ==========================================
                // AUTOMATION & RETARGETING
                // ==========================================
                SC\Section::make('Automation & Retargeting')
                    ->schema([
                        Forms\Components\Toggle::make('auto_optimize')
                            ->label('Auto-Optimize')
                            ->default(true)
                            ->helperText('Automatically adjust budgets, pause underperformers, and reallocate spending'),

                        Forms\Components\Toggle::make('retargeting_enabled')
                            ->label('Enable Retargeting')
                            ->default(true)
                            ->reactive()
                            ->helperText('Automatically retarget website visitors and past customers'),

                        Forms\Components\KeyValue::make('optimization_rules')
                            ->label('Optimization Thresholds')
                            ->keyLabel('Rule')
                            ->valueLabel('Value')
                            ->default([
                                'max_cpc' => '3.00',
                                'min_ctr' => '0.5',
                                'min_roas' => '1.5',
                                'max_frequency' => '5',
                            ])
                            ->helperText('Auto-optimization triggers'),

                        Forms\Components\KeyValue::make('retargeting_config')
                            ->label('Retargeting Settings')
                            ->visible(fn (Forms\Get $get) => $get('retargeting_enabled'))
                            ->default([
                                'website_visitors' => 'true',
                                'cart_abandoners' => 'true',
                                'past_attendees' => 'true',
                                'lookalike_percentage' => '2',
                            ]),
                    ])->columns(2)->collapsible(),

                // ==========================================
                // UTM TRACKING
                // ==========================================
                SC\Section::make('UTM Tracking')
                    ->schema([
                        Forms\Components\TextInput::make('utm_source')
                            ->default('tixello_ads')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('utm_medium')
                            ->default('paid_social')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('utm_campaign')
                            ->maxLength(100)
                            ->helperText('Auto-generated from campaign name if empty'),
                        Forms\Components\TextInput::make('utm_content')
                            ->maxLength(100),
                    ])->columns(4)->collapsible()->collapsed(),

                // ==========================================
                // STATUS
                // ==========================================
                SC\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_review' => 'Pending Review',
                                'approved' => 'Approved',
                                'launching' => 'Launching...',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'optimizing' => 'Optimizing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\Textarea::make('status_notes')
                            ->rows(2),
                    ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->name),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ro'] ?? '-') : $state)
                    ->limit(25)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Organizer')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => fn ($state) => in_array($state, ['pending_review', 'launching', 'optimizing']),
                        'success' => fn ($state) => in_array($state, ['active', 'approved']),
                        'danger' => fn ($state) => in_array($state, ['failed', 'paused']),
                        'info' => 'completed',
                    ]),

                Tables\Columns\TextColumn::make('target_platforms')
                    ->label('Platforms')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map('ucfirst', $state)) : $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('total_budget')
                    ->label('Budget')
                    ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_spend')
                    ->label('Spent')
                    ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                    ->sortable()
                    ->color(fn ($record) => $record->budget_utilization > 90 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('total_impressions')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_clicks')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_conversions')
                    ->label('Conv.')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roas')
                    ->label('ROAS')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . 'x' : '-')
                    ->color(fn ($state) => $state >= 2 ? 'success' : ($state >= 1 ? 'warning' : 'danger'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('avg_cpc')
                    ->label('CPC')
                    ->money('eur')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->dateTime('M d')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End')
                    ->dateTime('M d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('objective')
                    ->options([
                        'conversions' => 'Conversions',
                        'traffic' => 'Traffic',
                        'awareness' => 'Awareness',
                        'engagement' => 'Engagement',
                        'leads' => 'Leads',
                    ]),
                Tables\Filters\Filter::make('active_campaigns')
                    ->label('Currently Running')
                    ->query(fn (Builder $query) => $query->whereIn('status', ['active', 'optimizing'])),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('launch')
                    ->label('Launch')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn ($record) => $record->canLaunch())
                    ->requiresConfirmation()
                    ->modalHeading('Launch Campaign')
                    ->modalDescription('This will create and activate ads on all selected platforms. Make sure creatives and targeting are properly configured.')
                    ->action(function (AdsCampaign $record) {
                        try {
                            app(\App\Services\AdsCampaign\AdsCampaignManager::class)->launch($record);
                            Notification::make()->success()->title('Campaign Launched')->body('Campaign is now live on all platforms.')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Launch Failed')->body($e->getMessage())->send();
                        }
                    }),
                Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record->canPause())
                    ->requiresConfirmation()
                    ->action(function (AdsCampaign $record) {
                        app(\App\Services\AdsCampaign\AdsCampaignManager::class)->pause($record, auth()->user(), 'Paused from admin');
                        Notification::make()->success()->title('Campaign Paused')->send();
                    }),
                Actions\Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->canResume())
                    ->action(function (AdsCampaign $record) {
                        app(\App\Services\AdsCampaign\AdsCampaignManager::class)->resume($record, auth()->user());
                        Notification::make()->success()->title('Campaign Resumed')->send();
                    }),
                Actions\Action::make('report')
                    ->label('Generate Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->visible(fn ($record) => $record->total_impressions > 0)
                    ->action(function (AdsCampaign $record) {
                        $report = app(\App\Services\AdsCampaign\ReportGenerator::class)->generate($record, 'weekly', null, null, auth()->user());
                        Notification::make()->success()->title('Report Generated')->body("Report #{$report->id} created.")->send();
                    }),
                Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (AdsCampaign $record) {
                        $new = app(\App\Services\AdsCampaign\AdsCampaignManager::class)->duplicate($record, null, auth()->user());
                        Notification::make()->success()->title('Campaign Duplicated')->body("New draft campaign #{$new->id} created.")->send();
                    }),
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
        return [
            RelationManagers\CreativesRelationManager::class,
            RelationManagers\TargetingRelationManager::class,
            RelationManagers\PlatformCampaignsRelationManager::class,
            RelationManagers\OptimizationLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdsCampaigns::route('/'),
            'create' => Pages\CreateAdsCampaign::route('/create'),
            'view' => Pages\ViewAdsCampaign::route('/{record}'),
            'edit' => Pages\EditAdsCampaign::route('/{record}/edit'),
        ];
    }
}

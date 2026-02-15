<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdsServiceRequestResource\Pages;
use App\Models\AdsCampaign\AdsServiceRequest;
use App\Models\Event;
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
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;

class AdsServiceRequestResource extends Resource
{
    protected static ?string $model = AdsServiceRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Service Requests';

    protected static \UnitEnum|string|null $navigationGroup = 'Ads Manager';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Ads Service Request';

    protected static ?string $pluralModelLabel = 'Ads Service Requests';

    protected static ?string $slug = 'ads-service-requests';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255),

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
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->getTranslation('title', 'en') ?? $e->getTranslation('title', 'ro') ?? "Event #{$e->id}"]);
                            })
                            ->searchable(),

                        Forms\Components\Textarea::make('brief')
                            ->label('Campaign Brief')
                            ->rows(4)
                            ->helperText('Organizer\'s description of what they want'),
                    ])->columns(2),

                SC\Section::make('Platforms & Creatives')
                    ->schema([
                        Forms\Components\CheckboxList::make('target_platforms')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'google' => 'Google Ads',
                            ])
                            ->columns(3)
                            ->required(),

                        Forms\Components\CheckboxList::make('creative_types')
                            ->options([
                                'image' => 'Image Ads',
                                'video' => 'Video Ads',
                                'carousel' => 'Carousel',
                            ])
                            ->columns(3),
                    ])->columns(2),

                SC\Section::make('Budget')
                    ->schema([
                        Forms\Components\TextInput::make('budget')
                            ->label('Ad Spend Budget')
                            ->numeric()
                            ->required()
                            ->prefix('EUR'),

                        Forms\Components\TextInput::make('service_fee')
                            ->label('Service Fee')
                            ->numeric()
                            ->default(0)
                            ->prefix('EUR')
                            ->helperText('Tixello management fee'),

                        Forms\Components\Select::make('currency')
                            ->options(['EUR' => 'EUR', 'RON' => 'RON', 'USD' => 'USD'])
                            ->default('EUR'),
                    ])->columns(3),

                SC\Section::make('Audience Preferences')
                    ->schema([
                        Forms\Components\KeyValue::make('audience_hints')
                            ->label('Target Audience Hints')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->helperText('age_min, age_max, locations, interests, languages, genders'),

                        Forms\Components\KeyValue::make('brand_guidelines')
                            ->label('Brand Guidelines')
                            ->helperText('colors, fonts, tone, logo usage'),
                    ])->collapsible(),

                SC\Section::make('Status & Review')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'refunded' => 'Refunded',
                                'failed' => 'Failed',
                            ])
                            ->default('pending'),

                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference'),

                        Forms\Components\Textarea::make('review_notes')
                            ->rows(2),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Organizer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ro'] ?? '-') : $state)
                    ->limit(25),

                Tables\Columns\TextColumn::make('target_platforms')
                    ->label('Platforms')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map('ucfirst', $state)) : $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('budget')
                    ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_fee')
                    ->label('Fee')
                    ->money('eur')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => fn ($state) => in_array($state, ['pending', 'under_review']),
                        'success' => fn ($state) => in_array($state, ['approved', 'completed']),
                        'danger' => fn ($state) => in_array($state, ['rejected', 'cancelled']),
                        'info' => 'in_progress',
                    ]),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['refunded', 'failed']),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->isPending())
                    ->requiresConfirmation()
                    ->action(function (AdsServiceRequest $record) {
                        $record->approve(auth()->user());
                        Notification::make()->success()->title('Request Approved')->send();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (AdsServiceRequest $record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);
                        Notification::make()->success()->title('Request Rejected')->send();
                    }),
                Actions\Action::make('create_campaign')
                    ->label('Create Campaign')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('primary')
                    ->visible(fn ($record) => $record->canCreateCampaign() && !$record->campaign)
                    ->action(function (AdsServiceRequest $record) {
                        $campaign = app(\App\Services\AdsCampaign\AdsCampaignManager::class)
                            ->createFromServiceRequest($record, auth()->user());
                        Notification::make()->success()
                            ->title('Campaign Created')
                            ->body("Campaign #{$campaign->id} created. Configure creatives and targeting, then launch.")
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdsServiceRequests::route('/'),
            'create' => Pages\CreateAdsServiceRequest::route('/create'),
            'edit' => Pages\EditAdsServiceRequest::route('/{record}/edit'),
        ];
    }
}

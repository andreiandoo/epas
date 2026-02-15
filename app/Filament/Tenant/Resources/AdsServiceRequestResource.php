<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\AdsServiceRequestResource\Pages;
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
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;

class AdsServiceRequestResource extends Resource
{
    protected static ?string $model = AdsServiceRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Ad Campaigns';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Ad Campaign Request';

    protected static ?string $pluralModelLabel = 'Ad Campaign Requests';

    protected static ?string $slug = 'ads-campaigns';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Campaign Request')
                    ->description('Tell us about the ad campaign you want for your event. We will handle the rest!')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Summer Festival Launch, Concert Promo'),

                        Forms\Components\Select::make('event_id')
                            ->label('Event to Promote')
                            ->options(function () use ($tenant) {
                                if (!$tenant) return [];
                                return Event::where('tenant_id', $tenant->id)
                                    ->where('is_cancelled', false)
                                    ->get()
                                    ->mapWithKeys(fn ($e) => [$e->id => $e->getTranslation('title', 'en') ?? $e->getTranslation('title', 'ro') ?? "Event #{$e->id}"]);
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Which event should this campaign promote?'),

                        Forms\Components\Textarea::make('brief')
                            ->label('Campaign Brief')
                            ->rows(4)
                            ->required()
                            ->placeholder("Describe your campaign goals, target audience, key messages, and anything specific you'd like us to know...")
                            ->helperText('The more detail you provide, the better we can target your audience'),
                    ])->columns(1),

                SC\Section::make('Platforms & Creative Types')
                    ->schema([
                        Forms\Components\CheckboxList::make('target_platforms')
                            ->label('Where do you want to advertise?')
                            ->options([
                                'facebook' => 'Facebook (Feed, Stories, Video)',
                                'instagram' => 'Instagram (Feed, Stories, Reels, Explore)',
                                'google' => 'Google Ads (Search, Display, YouTube)',
                            ])
                            ->default(['facebook', 'instagram'])
                            ->required()
                            ->columns(1)
                            ->helperText('We recommend Facebook + Instagram for event promotion'),

                        Forms\Components\CheckboxList::make('creative_types')
                            ->label('What type of ads do you want?')
                            ->options([
                                'image' => 'Image Ads (static visuals)',
                                'video' => 'Video Ads (motion, trailers, reels)',
                                'carousel' => 'Carousel (multiple images/slides)',
                            ])
                            ->default(['image'])
                            ->columns(1)
                            ->helperText('Upload your materials below, or we can create them for you'),
                    ])->columns(2),

                SC\Section::make('Budget')
                    ->description('Set your advertising budget. This is the amount that will be spent on ads across all selected platforms.')
                    ->schema([
                        Forms\Components\TextInput::make('budget')
                            ->label('Ad Spend Budget')
                            ->numeric()
                            ->required()
                            ->prefix('EUR')
                            ->minValue(50)
                            ->step(10)
                            ->helperText('Minimum 50 EUR. We recommend at least 200 EUR for meaningful results.'),

                        Forms\Components\Select::make('currency')
                            ->options(['EUR' => 'EUR', 'RON' => 'RON', 'USD' => 'USD'])
                            ->default('EUR'),

                        Forms\Components\Placeholder::make('budget_guide')
                            ->label('')
                            ->content('Budget guide: 50-100 EUR = local reach | 200-500 EUR = regional impact | 500+ EUR = maximum visibility'),
                    ])->columns(2),

                SC\Section::make('Target Audience (Optional)')
                    ->description('Help us understand who you want to reach. Leave empty for our default targeting.')
                    ->schema([
                        Forms\Components\KeyValue::make('audience_hints')
                            ->label('Audience Preferences')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->addActionLabel('Add preference')
                            ->helperText('Examples: age_min = 18, age_max = 45, locations = Bucharest, interests = music/festivals'),
                    ])->collapsible()->collapsed(),

                SC\Section::make('Brand & Materials (Optional)')
                    ->description('Upload your event visuals, logos, and brand guidelines.')
                    ->schema([
                        Forms\Components\FileUpload::make('materials')
                            ->label('Upload Images/Videos')
                            ->multiple()
                            ->disk('public')
                            ->directory('ads-materials')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4'])
                            ->maxSize(50 * 1024)
                            ->helperText('Upload event posters, videos, or any visuals for the ads'),

                        Forms\Components\KeyValue::make('brand_guidelines')
                            ->label('Brand Guidelines')
                            ->addActionLabel('Add guideline')
                            ->helperText('Examples: primary_color = #FF5733, tone = energetic, logo_usage = always include'),
                    ])->collapsible()->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ro'] ?? '-') : ($state ?: '-'))
                    ->limit(25),

                Tables\Columns\TextColumn::make('target_platforms')
                    ->label('Platforms')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map('ucfirst', $state)) : '-')
                    ->badge(),

                Tables\Columns\TextColumn::make('budget')
                    ->money(fn ($record) => strtolower($record->currency ?? 'eur'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => fn ($state) => in_array($state, ['pending', 'under_review']),
                        'success' => fn ($state) => in_array($state, ['approved', 'completed', 'in_progress']),
                        'danger' => fn ($state) => in_array($state, ['rejected', 'cancelled']),
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Submitted',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'in_progress' => 'Campaign Running',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($state),
                    }),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => fn ($state) => in_array($state, ['refunded', 'failed']),
                    ]),

                // Show campaign results when available
                Tables\Columns\TextColumn::make('campaign.total_conversions')
                    ->label('Conversions')
                    ->default('-')
                    ->numeric(),

                Tables\Columns\TextColumn::make('campaign.roas')
                    ->label('ROAS')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . 'x' : '-')
                    ->color(fn ($state) => $state && (float)$state >= 2 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review']))
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'cancelled'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdsServiceRequests::route('/'),
            'create' => Pages\CreateAdsServiceRequest::route('/create'),
        ];
    }
}

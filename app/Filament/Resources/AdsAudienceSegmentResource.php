<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdsAudienceSegmentResource\Pages;
use App\Models\AdsCampaign\AdsAudienceSegment;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class AdsAudienceSegmentResource extends Resource
{
    protected static ?string $model = AdsAudienceSegment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Audience Segments';

    protected static \UnitEnum|string|null $navigationGroup = 'Ads Manager';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Audience Segment';

    protected static ?string $slug = 'ads-audiences';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Segment Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2),

                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->options([
                                'custom' => 'Custom Upload',
                                'website_visitors' => 'Website Visitors',
                                'past_attendees' => 'Past Event Attendees',
                                'cart_abandoners' => 'Cart Abandoners',
                                'lookalike' => 'Lookalike Audience',
                                'engaged_users' => 'Engaged Users',
                                'email_subscribers' => 'Email Subscribers',
                                'high_value' => 'High-Value Customers',
                            ])
                            ->required(),
                    ])->columns(2),

                SC\Section::make('Source Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('source_config')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->helperText('event_ids (comma-sep), days_back, min_spend'),
                    ]),

                SC\Section::make('Platform Sync')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_audience_id')
                            ->label('Facebook Audience ID'),
                        Forms\Components\TextInput::make('google_audience_id')
                            ->label('Google Audience ID'),
                        Forms\Components\Toggle::make('auto_sync')
                            ->label('Auto-Sync Daily')
                            ->default(true),
                        Forms\Components\TextInput::make('estimated_size')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'past_attendees' => 'success',
                        'cart_abandoners' => 'warning',
                        'lookalike' => 'info',
                        'high_value' => 'primary',
                        'website_visitors' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Organizer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimated_size')
                    ->label('Size')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('facebook_audience_id')
                    ->label('FB')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->facebook_audience_id)),

                Tables\Columns\IconColumn::make('google_audience_id')
                    ->label('Google')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->google_audience_id)),

                Tables\Columns\IconColumn::make('auto_sync')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Actions\Action::make('sync_now')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (AdsAudienceSegment $record) {
                        dispatch(new \App\Jobs\AdsCampaign\SyncAdsAudienceSegments());
                        Notification::make()->success()->title('Sync job dispatched')->send();
                    }),
            ])
            ->headerActions([])
            ->defaultSort('estimated_size', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdsAudienceSegments::route('/'),
            'create' => Pages\CreateAdsAudienceSegment::route('/create'),
            'edit' => Pages\EditAdsAudienceSegment::route('/{record}/edit'),
        ];
    }
}

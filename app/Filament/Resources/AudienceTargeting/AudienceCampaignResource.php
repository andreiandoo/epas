<?php

namespace App\Filament\Resources\AudienceTargeting;

use App\Filament\Resources\AudienceTargeting\AudienceCampaignResource\Pages;
use App\Models\AudienceCampaign;
use App\Models\AudienceSegment;
use App\Models\Event;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AudienceCampaignResource extends Resource
{
    protected static ?string $model = AudienceCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign Details')
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->options(Tenant::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2),

                        Forms\Components\Select::make('segment_id')
                            ->label('Target Segment')
                            ->options(fn (callable $get) =>
                                AudienceSegment::where('tenant_id', $get('tenant_id'))
                                    ->where('status', 'active')
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('event_id')
                            ->label('Related Event (optional)')
                            ->options(fn (callable $get) =>
                                Event::where('tenant_id', $get('tenant_id'))
                                    ->where('event_date', '>=', now())
                                    ->pluck('title', 'id')
                            )
                            ->searchable(),

                        Forms\Components\Select::make('campaign_type')
                            ->options([
                                'email' => 'Email Campaign',
                                'meta_ads' => 'Meta Ads (Facebook/Instagram)',
                                'google_ads' => 'Google Ads',
                                'tiktok_ads' => 'TikTok Ads',
                                'multi_channel' => 'Multi-Channel',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'completed' => 'Completed',
                            ])
                            ->default('draft')
                            ->disabled(fn ($record) => $record && in_array($record->status, ['active', 'completed'])),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule For')
                            ->visible(fn (callable $get) => $get('status') === 'scheduled'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Email Settings')
                    ->schema([
                        Forms\Components\TextInput::make('settings.subject')
                            ->label('Email Subject')
                            ->required(),

                        Forms\Components\TextInput::make('settings.sender_name')
                            ->label('Sender Name')
                            ->required(),

                        Forms\Components\TextInput::make('settings.sender_email')
                            ->label('Sender Email')
                            ->email()
                            ->required(),

                        Forms\Components\RichEditor::make('settings.html_content')
                            ->label('Email Content')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (callable $get) => $get('campaign_type') === 'email'),

                Forms\Components\Section::make('Ad Campaign Settings')
                    ->schema([
                        Forms\Components\TextInput::make('settings.budget_cents')
                            ->label('Budget (cents)')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('settings.duration_days')
                            ->label('Duration (days)')
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('settings.objective')
                            ->label('Campaign Objective')
                            ->options([
                                'awareness' => 'Brand Awareness',
                                'traffic' => 'Website Traffic',
                                'conversions' => 'Conversions',
                                'engagement' => 'Engagement',
                            ])
                            ->required(),
                    ])
                    ->columns(2)
                    ->visible(fn (callable $get) => in_array($get('campaign_type'), ['meta_ads', 'google_ads', 'tiktok_ads'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('segment.name')
                    ->label('Segment')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('campaign_type')
                    ->colors([
                        'info' => 'email',
                        'primary' => 'meta_ads',
                        'success' => 'google_ads',
                        'warning' => 'tiktok_ads',
                        'secondary' => 'multi_channel',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'email' => 'Email',
                        'meta_ads' => 'Meta',
                        'google_ads' => 'Google',
                        'tiktok_ads' => 'TikTok',
                        'multi_channel' => 'Multi',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'active',
                        'info' => 'paused',
                        'primary' => 'completed',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(Tenant::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('campaign_type')
                    ->options([
                        'email' => 'Email',
                        'meta_ads' => 'Meta Ads',
                        'google_ads' => 'Google Ads',
                        'tiktok_ads' => 'TikTok Ads',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('launch')
                    ->label('Launch')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AudienceCampaign $record) {
                        app(\App\Services\AudienceTargeting\CampaignOrchestrationService::class)
                            ->launchCampaign($record);
                    })
                    ->visible(fn (AudienceCampaign $record) => $record->canLaunch()),

                Tables\Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->action(function (AudienceCampaign $record) {
                        $record->update(['status' => 'paused']);
                    })
                    ->visible(fn (AudienceCampaign $record) => $record->canPause()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AudienceCampaign $record) => $record->status === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAudienceCampaigns::route('/'),
            'create' => Pages\CreateAudienceCampaign::route('/create'),
            'edit' => Pages\EditAudienceCampaign::route('/{record}/edit'),
        ];
    }
}

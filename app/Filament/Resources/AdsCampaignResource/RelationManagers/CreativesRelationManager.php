<?php

namespace App\Filament\Resources\AdsCampaignResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Notifications\Notification;

class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

    protected static ?string $title = 'Ad Creatives';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Creative Content')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Creative Type')
                            ->options([
                                'image' => 'Single Image',
                                'video' => 'Video',
                                'carousel' => 'Carousel',
                                'stories' => 'Stories Format',
                                'reels' => 'Reels/Shorts',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('headline')
                            ->label('Headline')
                            ->maxLength(255)
                            ->required()
                            ->helperText('Facebook: max 40 chars, Google: max 30 chars'),

                        Forms\Components\Textarea::make('primary_text')
                            ->label('Primary Text / Ad Copy')
                            ->rows(3)
                            ->helperText('The main text above the image/video'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->helperText('Additional description shown below headline'),

                        Forms\Components\Select::make('cta_type')
                            ->label('Call to Action')
                            ->options([
                                'GET_TICKETS' => 'Get Tickets',
                                'BOOK_NOW' => 'Book Now',
                                'LEARN_MORE' => 'Learn More',
                                'SIGN_UP' => 'Sign Up',
                                'SHOP_NOW' => 'Shop Now',
                                'WATCH_MORE' => 'Watch More',
                                'GET_OFFER' => 'Get Offer',
                            ])
                            ->default('GET_TICKETS'),

                        Forms\Components\TextInput::make('cta_url')
                            ->label('Destination URL')
                            ->url()
                            ->required(),

                        Forms\Components\TextInput::make('display_url')
                            ->label('Display URL (optional)')
                            ->placeholder('tixello.com/event-name'),
                    ])->columns(2),

                SC\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('media_path')
                            ->label('Upload Image/Video')
                            ->disk('public')
                            ->directory('ads-creatives')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/mov'])
                            ->maxSize(100 * 1024) // 100MB for videos
                            ->helperText('Images: JPG/PNG/WebP (max 30MB). Videos: MP4/MOV (max 100MB)'),

                        Forms\Components\FileUpload::make('thumbnail_path')
                            ->label('Video Thumbnail (optional)')
                            ->disk('public')
                            ->directory('ads-creatives/thumbnails')
                            ->image()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'video'),
                    ]),

                SC\Section::make('A/B Testing & Status')
                    ->schema([
                        Forms\Components\Select::make('variant_label')
                            ->label('A/B Variant')
                            ->options([
                                'A' => 'Variant A',
                                'B' => 'Variant B',
                                'C' => 'Variant C',
                            ])
                            ->placeholder('No variant (single creative)'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_review' => 'Pending Review',
                                'approved' => 'Approved',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft'),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected'),
                    ])->columns(2),

                SC\Section::make('Platform-Specific Overrides')
                    ->schema([
                        Forms\Components\KeyValue::make('facebook_overrides')
                            ->label('Facebook Overrides'),
                        Forms\Components\KeyValue::make('instagram_overrides')
                            ->label('Instagram Overrides'),
                        Forms\Components\KeyValue::make('google_overrides')
                            ->label('Google Ads Overrides'),
                    ])->collapsible()->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('media_path')
                    ->label('Preview')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->isVideo() ? asset('images/video-placeholder.png') : null),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),

                Tables\Columns\TextColumn::make('headline')
                    ->limit(30),

                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variant')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'A' => 'info',
                        'B' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending_review',
                        'success' => fn ($state) => in_array($state, ['approved', 'active']),
                        'danger' => fn ($state) => in_array($state, ['rejected', 'paused']),
                    ]),

                Tables\Columns\IconColumn::make('is_winner')
                    ->label('Winner')
                    ->boolean()
                    ->trueIcon('heroicon-o-trophy')
                    ->falseIcon(''),

                Tables\Columns\TextColumn::make('impressions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . '%' : '-')
                    ->sortable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending_review')
                    ->action(fn ($record) => $record->approve()),
                Actions\Action::make('validate')
                    ->label('Check Specs')
                    ->icon('heroicon-o-shield-check')
                    ->action(function ($record) {
                        $errors = [];
                        foreach (['facebook', 'instagram', 'google'] as $platform) {
                            $platformErrors = $record->validateForPlatform($platform);
                            if (!empty($platformErrors)) {
                                $errors[$platform] = $platformErrors;
                            }
                        }
                        if (empty($errors)) {
                            Notification::make()->success()->title('All specs valid!')->send();
                        } else {
                            $msg = '';
                            foreach ($errors as $p => $errs) {
                                $msg .= ucfirst($p) . ": " . implode(', ', $errs) . "\n";
                            }
                            Notification::make()->warning()->title('Spec Warnings')->body($msg)->send();
                        }
                    }),
                Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}

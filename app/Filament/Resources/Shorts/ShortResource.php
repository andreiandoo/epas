<?php

namespace App\Filament\Resources\Shorts;

use App\Filament\Resources\Shorts\Pages\CreateShort;
use App\Filament\Resources\Shorts\Pages\EditShort;
use App\Filament\Resources\Shorts\Pages\ListShorts;
use App\Jobs\Shorts\IngestShortJob;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Central curation surface for shorts.
 *
 * Deliberately without getEloquentQuery() scoping: core admin curates the global
 * feed across every tenant/marketplace (same stance as the Media Library). The
 * per-organiser twin lives under App\Filament\Tenant\Resources.
 */
class ShortResource extends Resource
{
    protected static ?string $model = Short::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-play-circle';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Shorts';

    protected static ?string $modelLabel = 'Short';

    protected static ?string $pluralModelLabel = 'Shorts';

    protected static ?int $navigationSort = 51;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(3)->schema([
                SC\Group::make()->columnSpan(2)->schema([
                    SC\Section::make('Source')
                        ->description('Native uploads stream as adaptive HLS. External links stay embeds — we never re-host third-party video.')
                        ->schema([
                            Forms\Components\Select::make('source')
                                ->options([
                                    'upload' => 'Native upload',
                                    'youtube' => 'YouTube',
                                    'tiktok' => 'TikTok',
                                    'instagram' => 'Instagram',
                                    'facebook' => 'Facebook',
                                ])
                                ->default('upload')
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('source_url')
                                ->label('Source link')
                                ->url()
                                ->maxLength(2048)
                                ->helperText('Paste the post link — metadata, embed and thumbnail are fetched from it.')
                                ->visible(fn (Forms\Get $get) => $get('source') !== 'upload')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('provider_asset_id')
                                ->label('Provider asset ID')
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Filled in by the upload session; playback URLs are signed per request.')
                                ->visible(fn (Forms\Get $get) => $get('source') === 'upload')
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('ready')
                                ->label('Asset ready')
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Set by the provider webhook once transcoding finishes.')
                                ->visible(fn (Forms\Get $get) => $get('source') === 'upload')
                                ->columnSpan(1),
                        ])
                        ->columns(2),

                    SC\Section::make('Presentation')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->maxLength(255)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('language')
                                ->maxLength(8)
                                ->helperText('ISO code — leaves the short visible to every locale when empty.')
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('caption')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\TagsInput::make('hashtags')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('music_credit')
                                ->helperText('Required for anything but silence — royalty-free library only.')
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('poster_path')
                                ->label('Cover')
                                ->image()
                                ->disk('public')
                                ->directory('shorts/posters')
                                ->helperText('Optional for native uploads — the provider generates one.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    SC\Section::make('Call to action')
                        ->description('What the "buy" button does when the viewer taps it.')
                        ->schema([
                            Forms\Components\Select::make('cta_type')
                                ->label('CTA')
                                ->options([
                                    'none' => 'None',
                                    'buy_tickets' => 'Buy tickets',
                                    'open_event' => 'Open event',
                                    'open_artist' => 'Open artist',
                                    'external' => 'External link',
                                ])
                                ->default('none')
                                ->live()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('cta_label')
                                ->maxLength(64)
                                ->visible(fn (Forms\Get $get) => $get('cta_type') !== 'none')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('cta_url')
                                ->url()
                                ->maxLength(2048)
                                ->visible(fn (Forms\Get $get) => $get('cta_type') === 'external')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('promo_code')
                                ->maxLength(64)
                                ->helperText('Pre-applied at checkout. Must exist in the coupon system.')
                                ->visible(fn (Forms\Get $get) => $get('cta_type') === 'buy_tickets')
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('cta_ticket_type_id')
                                ->label('Ticket type ID')
                                ->numeric()
                                ->helperText('Pre-selects this ticket in checkout.')
                                ->visible(fn (Forms\Get $get) => $get('cta_type') === 'buy_tickets')
                                ->columnSpan(1),
                        ])
                        ->columns(2),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    SC\Section::make('Publishing')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'draft' => 'Draft',
                                    'pending_review' => 'Pending review',
                                    'published' => 'Published',
                                    'archived' => 'Archived',
                                    'rejected' => 'Rejected',
                                ])
                                ->default('draft')
                                ->required(),

                            Forms\Components\Toggle::make('is_featured')
                                ->label('Featured')
                                ->helperText('Pinned to the top of the editorial feed.'),

                            Forms\Components\DateTimePicker::make('published_at')
                                ->seconds(false),

                            Forms\Components\DateTimePicker::make('expires_at')
                                ->seconds(false)
                                ->helperText('Drops out of the feed automatically once passed.'),

                            Forms\Components\TextInput::make('sort')
                                ->numeric()
                                ->default(0),
                        ]),

                    SC\Section::make('Attachment')
                        ->description('Leave the owner empty for an editorial short.')
                        ->schema([
                            Forms\Components\MorphToSelect::make('owner')
                                ->label('Owner')
                                ->types([
                                    Forms\Components\MorphToSelect\Type::make(Event::class)
                                        ->titleAttribute('title'),
                                    Forms\Components\MorphToSelect\Type::make(Artist::class)
                                        ->titleAttribute('name'),
                                    Forms\Components\MorphToSelect\Type::make(Tenant::class)
                                        ->titleAttribute('name'),
                                ])
                                ->searchable(),

                            Forms\Components\TextInput::make('event_id')
                                ->label('Event ID')
                                ->numeric()
                                ->helperText('Denormalised so event feeds stay a single index lookup.'),

                            Forms\Components\TextInput::make('tenant_id')
                                ->label('Tenant ID')
                                ->numeric(),

                            Forms\Components\TextInput::make('marketplace_client_id')
                                ->label('Marketplace client ID')
                                ->numeric()
                                ->helperText('Empty = visible on every marketplace.'),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('poster_path')
                    ->label('Cover')
                    ->disk('public')
                    ->height(56),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->weight('bold')
                    ->description(fn (Short $record) => $record->caption ? str($record->caption)->limit(60) : null),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state) => $state === 'upload' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : 'Editorial')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'pending_review' => 'warning',
                        'rejected' => 'danger',
                        'archived' => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\IconColumn::make('ready')
                    ->label('Ready')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('avg_watch_ratio')
                    ->label('Watch %')
                    ->formatStateUsing(fn ($state) => number_format(((float) $state) * 100, 1).'%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cta_clicks')
                    ->label('CTA')
                    ->numeric()
                    ->toggleable(),

                // CTR and CVR are derived, not stored: they would go stale the
                // moment either side moved (docs/plans/shorts.md B1).
                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn (Short $record) => $record->views > 0
                        ? number_format($record->cta_clicks / $record->views * 100, 1).'%'
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('conversions')
                    ->label('Sales')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cvr')
                    ->label('CVR')
                    ->state(fn (Short $record) => $record->cta_clicks > 0
                        ? number_format($record->conversions / $record->cta_clicks * 100, 1).'%'
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('revenue_cents')
                    ->label('Revenue')
                    ->state(fn (Short $record) => $record->revenue_cents > 0
                        ? number_format($record->revenue_cents / 100, 2).' '.($record->revenue_currency ?? '')
                        : '—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_review' => 'Pending review',
                        'published' => 'Published',
                        'archived' => 'Archived',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'upload' => 'Native upload',
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
                Tables\Filters\TernaryFilter::make('ready')->label('Asset ready'),
            ])
            ->recordActions([
                // "Fetch from link" — reads metadata, embed code and thumbnail
                // from the platform. The video file is never downloaded; that
                // would break ToS and copyright on all four platforms.
                Action::make('ingest')
                    ->label('Fetch from link')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Short $record) => $record->source !== Short::SOURCE_UPLOAD && $record->source_url)
                    ->action(function (Short $record) {
                        IngestShortJob::dispatch($record->id);

                        Notification::make()
                            ->title('Fetching — the short fills in shortly')
                            ->info()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $blocked = 0;

                            foreach ($records as $record) {
                                // A native short that has not finished transcoding
                                // would render as a broken player in the feed.
                                if ($record->source === Short::SOURCE_UPLOAD && ! $record->ready) {
                                    $blocked++;

                                    continue;
                                }

                                $record->update([
                                    'status' => Short::STATUS_PUBLISHED,
                                    'published_at' => $record->published_at ?? now(),
                                ]);
                            }

                            Notification::make()
                                ->title($blocked > 0 ? "Published — {$blocked} skipped (asset not ready)" : 'Published')
                                ->color($blocked > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    BulkAction::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => Short::STATUS_ARCHIVED])),

                    BulkAction::make('ingest')
                        ->label('Fetch from link')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $queued = 0;

                            foreach ($records as $record) {
                                if ($record->source === Short::SOURCE_UPLOAD || ! $record->source_url) {
                                    continue;
                                }

                                IngestShortJob::dispatch($record->id);
                                $queued++;
                            }

                            Notification::make()
                                ->title("Fetching {$queued} short(s)")
                                ->info()
                                ->send();
                        }),

                    BulkAction::make('feature')
                        ->label('Toggle featured')
                        ->icon('heroicon-o-star')
                        ->action(fn (Collection $records) => $records->each(
                            fn (Short $record) => $record->update(['is_featured' => ! $record->is_featured])
                        )),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShorts::route('/'),
            'create' => CreateShort::route('/create'),
            'edit' => EditShort::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShortResource\Pages\CreateShort;
use App\Filament\Tenant\Resources\ShortResource\Pages\EditShort;
use App\Filament\Tenant\Resources\ShortResource\Pages\ListShorts;
use App\Models\Short;
use App\Models\TicketType;
use App\Services\Shorts\ShortAttributionService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Organiser-facing twin of the core Shorts resource.
 *
 * Unlike the core one, this IS scoped: getEloquentQuery() narrows to the
 * tenant's own shorts, following the pattern in EventResource.
 *
 * Organisers cannot publish to the global feed themselves — a short they create
 * goes to pending_review and central moderation decides (docs/plans/shorts.md
 * §14). The status field is therefore read-only here.
 */
class ShortResource extends Resource
{
    protected static ?string $model = Short::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-play-circle';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Shorts';

    protected static ?string $modelLabel = 'Short';

    protected static ?string $pluralModelLabel = 'Shorts';

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()?->tenant_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Source')
                ->description('Upload a vertical video, or paste a link to one you already posted.')
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
                        ->live(),

                    Forms\Components\TextInput::make('source_url')
                        ->label('Source link')
                        ->url()
                        ->maxLength(2048)
                        ->visible(fn (Forms\Get $get) => $get('source') !== 'upload'),
                ])
                ->columns(2),

            SC\Section::make('Presentation')
                ->schema([
                    Forms\Components\TextInput::make('title')->maxLength(255),
                    Forms\Components\TextInput::make('language')->maxLength(8),
                    Forms\Components\Textarea::make('caption')->rows(3)->columnSpanFull(),
                    Forms\Components\TagsInput::make('hashtags')->columnSpanFull(),
                    Forms\Components\TextInput::make('music_credit')
                        ->helperText('Royalty-free library only — unlicensed music gets the short taken down.')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('poster_path')
                        ->label('Cover')
                        ->image()
                        ->disk('public')
                        ->directory('shorts/posters')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            SC\Section::make('Sell from this short')
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Event')
                        ->relationship(
                            'event',
                            'title',
                            fn (Builder $query) => $query->where('tenant_id', auth()->user()?->tenant_id),
                        )
                        ->searchable()
                        ->preload()
                        ->live(),

                    Forms\Components\Select::make('cta_type')
                        ->label('Button')
                        ->options([
                            'none' => 'None',
                            'buy_tickets' => 'Buy tickets',
                            'open_event' => 'Open event',
                        ])
                        ->default('none')
                        ->live(),

                    Forms\Components\TextInput::make('cta_label')
                        ->maxLength(64)
                        ->visible(fn (Forms\Get $get) => $get('cta_type') !== 'none'),

                    Forms\Components\Select::make('cta_ticket_type_id')
                        ->label('Ticket')
                        ->options(fn (Forms\Get $get) => $get('event_id')
                            ? TicketType::query()
                                ->where('event_id', $get('event_id'))
                                ->pluck('name', 'id')
                                ->all()
                            : [])
                        ->helperText('Pre-selected at checkout.')
                        ->visible(fn (Forms\Get $get) => $get('cta_type') === 'buy_tickets'),

                    Forms\Components\TextInput::make('promo_code')
                        ->maxLength(64)
                        ->helperText('Must be an existing coupon code.')
                        ->visible(fn (Forms\Get $get) => $get('cta_type') === 'buy_tickets'),
                ])
                ->columns(2),

            SC\Section::make('Status')
                ->description('New shorts go to review before they reach the global feed.')
                ->schema([
                    Forms\Components\TextInput::make('status')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->seconds(false)
                        ->helperText('Optional — drops out of the feed automatically.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('poster_path')
                    ->label('Cover')
                    ->disk('public')
                    ->height(48),

                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'pending_review' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('views')->numeric()->sortable(),

                Tables\Columns\TextColumn::make('avg_watch_ratio')
                    ->label('Watch %')
                    ->formatStateUsing(fn ($state) => number_format(((float) $state) * 100, 1).'%'),

                Tables\Columns\TextColumn::make('cta_clicks')->label('CTA')->numeric(),

                // CTR/CVR through the attribution service rather than recomputed
                // here: one definition of the rate, and it was previously
                // implemented and never called by anything.
                Tables\Columns\TextColumn::make('ctr')
                    ->label('CTR')
                    ->state(fn (Short $record) => number_format(
                        app(ShortAttributionService::class)->rates($record)['ctr'] * 100, 1
                    ).'%'),

                Tables\Columns\TextColumn::make('conversions')->label('Sales')->numeric(),

                Tables\Columns\TextColumn::make('cvr')
                    ->label('CVR')
                    ->state(fn (Short $record) => number_format(
                        app(ShortAttributionService::class)->rates($record)['cvr'] * 100, 1
                    ).'%'),

                Tables\Columns\TextColumn::make('revenue_cents')
                    ->label('Revenue')
                    ->state(fn (Short $record) => $record->revenue_cents > 0
                        ? number_format($record->revenue_cents / 100, 2).' '.($record->revenue_currency ?? '')
                        : '—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'pending_review' => 'Pending review',
                    'published' => 'Published',
                    'archived' => 'Archived',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([EditAction::make()])
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

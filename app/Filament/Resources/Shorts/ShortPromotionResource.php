<?php

namespace App\Filament\Resources\Shorts;

use App\Filament\Resources\Shorts\ShortPromotionResource\Pages\CreateShortPromotion;
use App\Filament\Resources\Shorts\ShortPromotionResource\Pages\EditShortPromotion;
use App\Filament\Resources\Shorts\ShortPromotionResource\Pages\ListShortPromotions;
use App\Models\Short;
use App\Models\ShortAdvertiser;
use App\Models\ShortPromotion;
use BackedEnum;
use Filament\Actions\Action;
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
 * Ad review — the gate every campaign passes through (D3).
 *
 * Campaigns land in `pending` from both sides: an organiser boosting an event,
 * and a brand ad created here. Nothing serves until it is approved, which is
 * the only reason the tenant panel can be trusted with a create form at all.
 *
 * This is also where brand ads are created, since an external advertiser has no
 * tenant panel to create one from.
 */
class ShortPromotionResource extends Resource
{
    protected static ?string $model = ShortPromotion::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Ad campaigns';

    protected static ?string $modelLabel = 'Campaign';

    protected static ?string $pluralModelLabel = 'Ad campaigns';

    protected static ?int $navigationSort = 54;

    /**
     * Pending campaigns are work waiting on us, so the count goes in the nav.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()->where('status', ShortPromotion::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Creative & advertiser')
                ->description('A brand ad is a published short like any other — same player, same rights checks, same telemetry. What makes it an ad is the campaign attached to it.')
                ->schema([
                    Forms\Components\Select::make('short_id')
                        ->label('Short')
                        ->options(fn () => Short::query()
                            ->where('status', Short::STATUS_PUBLISHED)
                            ->orderByDesc('published_at')
                            ->limit(300)
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('short_advertiser_id')
                        ->label('Advertiser')
                        ->options(fn () => ShortAdvertiser::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->helperText('Who pays. House advertisers are never billed.'),

                    Forms\Components\Select::make('objective')
                        ->label('Objective')
                        ->options([
                            ShortPromotion::OBJECTIVE_EVENT => 'Event — sell tickets',
                            ShortPromotion::OBJECTIVE_ARTIST => 'Artist — grow an audience',
                            ShortPromotion::OBJECTIVE_BRAND => 'Brand — third-party advertising',
                            ShortPromotion::OBJECTIVE_HOUSE => 'House — our own cross-promotion',
                        ])
                        ->default(ShortPromotion::OBJECTIVE_EVENT)
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('disclosure_label')
                        ->label('Disclosure label')
                        ->maxLength(64)
                        ->placeholder(fn (Forms\Get $get) => match ($get('objective')) {
                            ShortPromotion::OBJECTIVE_BRAND => config('shorts.ads.labels.brand', 'Reclamă'),
                            ShortPromotion::OBJECTIVE_HOUSE => config('shorts.ads.labels.house'),
                            default => config('shorts.ads.labels.default', 'Sponsorizat'),
                        })
                        ->helperText('Leave blank for the default wording for this objective. It is always shown — it cannot be turned off.'),

                    Forms\Components\TextInput::make('tenant_id')
                        ->label('Tenant ID')
                        ->numeric()
                        ->helperText('Only for organiser boosts — leave empty for a brand ad.'),
                ])
                ->columns(2),

            SC\Section::make('Budget & pacing')
                ->schema([
                    Forms\Components\Select::make('model')
                        ->options([
                            ShortPromotion::MODEL_CPM => 'CPM — per 1000 impressions',
                            ShortPromotion::MODEL_CPC => 'CPC — per CTA click',
                        ])
                        ->default(ShortPromotion::MODEL_CPM)
                        ->required(),

                    Forms\Components\TextInput::make('bid_cents')->label('Bid (minor units)')->numeric()->minValue(0)->required(),

                    Forms\Components\TextInput::make('budget_cents')->label('Budget (minor units)')->numeric()->minValue(1)->required(),

                    Forms\Components\TextInput::make('priority')
                        ->label('Priority')
                        ->numeric()
                        ->default(0)
                        ->helperText('Tie-break within a lane. House ads only ever fill slots no paid campaign wanted.'),

                    Forms\Components\TextInput::make('frequency_cap')
                        ->label('Impressions/day/viewer')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->placeholder((string) config('shorts.ads.frequency_cap', 3)),

                    Forms\Components\DateTimePicker::make('start_at')->seconds(false),

                    Forms\Components\DateTimePicker::make('end_at')->seconds(false)->after('start_at'),
                ])
                ->columns(2),

            SC\Section::make('Targeting')
                ->description('Country and age fail closed: a viewer we cannot place, or whose age we do not know, is not served a campaign that asked for either. Genres are a relevance hint and fail open.')
                ->schema([
                    Forms\Components\TagsInput::make('targeting.geo')->label('Countries (ISO codes)'),
                    Forms\Components\TextInput::make('targeting.age.min')->label('Min age')->numeric(),
                    Forms\Components\TextInput::make('targeting.age.max')->label('Max age')->numeric(),
                    Forms\Components\TagsInput::make('targeting.genres')->label('Event genre IDs'),
                ])
                ->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('short.title')->label('Short')->limit(28)->searchable(),

                Tables\Columns\TextColumn::make('advertiser.name')->label('Advertiser')->searchable(),

                Tables\Columns\TextColumn::make('objective')->badge()->color(fn (?string $state) => match ($state) {
                    ShortPromotion::OBJECTIVE_BRAND => 'warning',
                    ShortPromotion::OBJECTIVE_HOUSE => 'info',
                    default => 'gray',
                }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ShortPromotion::STATUS_ACTIVE => 'success',
                        ShortPromotion::STATUS_PENDING => 'warning',
                        ShortPromotion::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('spend')
                    ->label('Spend')
                    ->state(fn (ShortPromotion $record) => number_format($record->spent_cents / 100, 2).
                        ' / '.number_format($record->budget_cents / 100, 2)),

                Tables\Columns\TextColumn::make('end_at')->label('Ends')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    ShortPromotion::STATUSES,
                    ShortPromotion::STATUSES,
                )),
                Tables\Filters\SelectFilter::make('objective')->options(array_combine(
                    ShortPromotion::OBJECTIVES,
                    ShortPromotion::OBJECTIVES,
                )),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ShortPromotion $record) => in_array(
                        $record->status,
                        [ShortPromotion::STATUS_PENDING, ShortPromotion::STATUS_REJECTED],
                        true,
                    ))
                    ->action(fn (ShortPromotion $record) => $record->update([
                        'status' => ShortPromotion::STATUS_ACTIVE,
                        'approved_at' => now(),
                        'rejection_reason' => null,
                    ])),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason')
                            ->required()
                            ->helperText('Shown to the advertiser — say what would make it approvable.'),
                    ])
                    ->visible(fn (ShortPromotion $record) => $record->status !== ShortPromotion::STATUS_REJECTED)
                    ->action(fn (ShortPromotion $record, array $data) => $record->update([
                        'status' => ShortPromotion::STATUS_REJECTED,
                        'approved_at' => null,
                        'rejection_reason' => $data['rejection_reason'],
                    ])),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Eager-loaded because the table renders the short's title and the
     * advertiser's name on every row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['short', 'advertiser']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortPromotions::route('/'),
            'create' => CreateShortPromotion::route('/create'),
            'edit' => EditShortPromotion::route('/{record}/edit'),
        ];
    }
}

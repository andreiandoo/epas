<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShortPromotionResource\Pages\CreateShortPromotion;
use App\Filament\Tenant\Resources\ShortPromotionResource\Pages\EditShortPromotion;
use App\Filament\Tenant\Resources\ShortPromotionResource\Pages\ListShortPromotions;
use App\Models\Short;
use App\Models\ShortAdvertiser;
use App\Models\ShortPromotion;
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
 * "Promovează short" — the organiser side of D3.
 *
 * An organiser can boost their own shorts and nobody else's, and cannot put a
 * campaign live themselves: a new flight lands in `pending` and central
 * moderation approves it, exactly like a short itself. That is the whole reason
 * status is not editable here — an unreviewed ad that can serve is an unreviewed
 * ad that will.
 */
class ShortPromotionResource extends Resource
{
    protected static ?string $model = ShortPromotion::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Promovare Shorts';

    protected static ?string $modelLabel = 'promovare';

    protected static ?string $pluralModelLabel = 'Promovări Shorts';

    protected static ?int $navigationSort = 31;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()?->tenant_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Ce promovezi')
                ->description('Poți promova doar shorts publicate care îți aparțin. Slotul e marcat vizibil ca sponsorizat în feed — asta nu se poate dezactiva.')
                ->schema([
                    Forms\Components\Select::make('short_id')
                        ->label('Short')
                        ->options(fn () => Short::query()
                            ->where('tenant_id', auth()->user()?->tenant_id)
                            ->where('status', Short::STATUS_PUBLISHED)
                            ->orderByDesc('published_at')
                            ->limit(200)
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('objective')
                        ->label('Obiectiv')
                        ->options([
                            ShortPromotion::OBJECTIVE_EVENT => 'Eveniment — vinzi bilete',
                            ShortPromotion::OBJECTIVE_ARTIST => 'Artist — crești audiența',
                        ])
                        ->default(ShortPromotion::OBJECTIVE_EVENT)
                        ->required(),
                ])
                ->columns(2),

            SC\Section::make('Buget')
                ->description('CPM se taxează la mia de afișări, CPC la click pe butonul de acțiune. Bugetul e distribuit uniform pe durata campaniei — nu se consumă în prima oră.')
                ->schema([
                    Forms\Components\Select::make('model')
                        ->label('Model de plată')
                        ->options([
                            ShortPromotion::MODEL_CPM => 'CPM — la 1000 de afișări',
                            ShortPromotion::MODEL_CPC => 'CPC — la click',
                        ])
                        ->default(ShortPromotion::MODEL_CPM)
                        ->required(),

                    Forms\Components\TextInput::make('bid_cents')
                        ->label('Licitație (bani)')
                        ->helperText('În bani. 5000 = 50 RON la mia de afișări.')
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    Forms\Components\TextInput::make('budget_cents')
                        ->label('Buget total (bani)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    Forms\Components\TextInput::make('frequency_cap')
                        ->label('Afișări/zi/persoană')
                        ->helperText('Gol = valoarea implicită a platformei.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20),

                    Forms\Components\DateTimePicker::make('start_at')->label('Start')->seconds(false),

                    Forms\Components\DateTimePicker::make('end_at')
                        ->label('Sfârșit')
                        ->seconds(false)
                        ->after('start_at'),
                ])
                ->columns(2),

            SC\Section::make('Targetare')
                ->description('Opțional. Fără țară, campania rulează oriunde. Cu țară setată, un vizitator a cărui locație nu poate fi determinată NU vede reclama.')
                ->schema([
                    Forms\Components\TagsInput::make('targeting.geo')
                        ->label('Țări (cod ISO, ex. RO)')
                        ->placeholder('RO'),

                    Forms\Components\TextInput::make('targeting.age.min')->label('Vârstă minimă')->numeric()->minValue(0)->maxValue(120),

                    Forms\Components\TextInput::make('targeting.age.max')->label('Vârstă maximă')->numeric()->minValue(0)->maxValue(120),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('short.title')->label('Short')->limit(30)->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ShortPromotion::STATUS_ACTIVE => 'success',
                        ShortPromotion::STATUS_PENDING => 'warning',
                        ShortPromotion::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('model')->label('Model')->badge(),

                Tables\Columns\TextColumn::make('budget_cents')
                    ->label('Buget')
                    ->state(fn (ShortPromotion $record) => number_format($record->budget_cents / 100, 2)),

                Tables\Columns\TextColumn::make('spent_cents')
                    ->label('Cheltuit')
                    ->state(fn (ShortPromotion $record) => number_format($record->spent_cents / 100, 2).
                        ' ('.($record->budget_cents > 0 ? round($record->spent_cents / $record->budget_cents * 100) : 0).'%)'),

                Tables\Columns\TextColumn::make('impressions')
                    ->label('Afișări')
                    ->state(fn (ShortPromotion $record) => $record->events()->where('type', 'impression')->count()),

                Tables\Columns\TextColumn::make('clicks')
                    ->label('Click-uri')
                    ->state(fn (ShortPromotion $record) => $record->events()->where('type', 'click')->count()),

                Tables\Columns\TextColumn::make('end_at')->label('Sfârșit')->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    ShortPromotion::STATUSES,
                    ShortPromotion::STATUSES,
                )),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortPromotions::route('/'),
            'create' => CreateShortPromotion::route('/create'),
            'edit' => EditShortPromotion::route('/{record}/edit'),
        ];
    }

    /**
     * Stamp the tenant and its advertiser row, and force the review status.
     *
     * Kept here rather than in the Create page so the same rule applies however
     * a record is made — a status an organiser can set is a review step an
     * organiser can skip.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stampOwnership(array $data): array
    {
        $tenantId = (int) auth()->user()?->tenant_id;

        $data['tenant_id'] = $tenantId;
        $data['status'] = ShortPromotion::STATUS_PENDING;
        $data['short_advertiser_id'] = ShortAdvertiser::forTenant(
            $tenantId,
            auth()->user()?->tenant?->name,
        )->id;

        return $data;
    }
}

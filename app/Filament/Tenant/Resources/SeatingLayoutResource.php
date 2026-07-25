<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\SeatingLayoutResource\Pages;
use App\Models\Scopes\TenantScope;
use App\Models\Seating\SeatingLayout;
use App\Models\Venue;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class SeatingLayoutResource extends Resource
{
    protected static ?string $model = SeatingLayout::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Hărți de sală';
    protected static ?string $modelLabel = 'Hartă de sală';
    protected static ?string $pluralModelLabel = 'Hărți de sală';

    public static function getEloquentQuery(): Builder
    {
        // TenantScope resolves auth()->user()->tenant_id, which is null for tenant-panel
        // users → it wouldn't filter and ALL layouts would leak. Filter explicitly instead:
        // show only layouts created by this tenant OR attached to one of its venues.
        $tenant = auth()->user()?->tenant;
        $tenantId = $tenant?->id;
        $venueIds = $tenantId
            ? Venue::where('tenant_id', $tenantId)->pluck('id')->all()
            : [];

        return parent::getEloquentQuery()
            ->withoutGlobalScope(TenantScope::class)
            ->where(function (Builder $q) use ($tenantId, $venueIds) {
                $q->where('seating_layouts.tenant_id', $tenantId);
                if (!empty($venueIds)) {
                    $q->orWhereIn('seating_layouts.venue_id', $venueIds);
                }
            });
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema->schema([
            Forms\Components\Hidden::make('tenant_id')->default($tenant?->id),

            SC\Section::make('Detalii')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Locație')
                        ->relationship('venue', 'name', fn (Builder $query) => $query->where('tenant_id', $tenant?->id))
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $name = $record->getTranslation('name', app()->getLocale()) ?? $record->getTranslation('name', 'en') ?? 'Locație';
                            return $record->city ? "{$name} ({$record->city})" : $name;
                        })
                        ->getOptionLabelUsing(function ($value) {
                            $record = Venue::find($value);
                            if (!$record) return $value;
                            $name = $record->getTranslation('name', app()->getLocale()) ?? $record->getTranslation('name', 'en') ?? 'Locație';
                            return $record->city ? "{$name} ({$record->city})" : $name;
                        })
                        ->searchable()->preload()->required()->columnSpanFull(),

                    Forms\Components\TextInput::make('name')->label('Nume model hartă')->required()->maxLength(255)->columnSpan(1),

                    Forms\Components\Select::make('status')->label('Status')
                        ->options(['draft' => 'Draft', 'published' => 'Publicat'])
                        ->default('draft')->required()->columnSpan(1),

                    Forms\Components\Textarea::make('notes')->label('Note')->maxLength(1000)->rows(2)->columnSpanFull(),
                ])->columns(2),

            SC\Section::make('Setări hartă')
                ->icon('heroicon-o-photo')
                ->schema([
                    Forms\Components\TextInput::make('canvas_w')->label('Lățime (px)')->required()->numeric()
                        ->default(config('seating.canvas.default_width', 1920))->columnSpan(1),
                    Forms\Components\TextInput::make('canvas_h')->label('Înălțime (px)')->required()->numeric()
                        ->default(config('seating.canvas.default_height', 1080))->columnSpan(1),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('venue.name')->label('Locație')
                    ->formatStateUsing(fn ($record) => $record->venue?->getTranslation('name', app()->getLocale()) ?? $record->venue?->getTranslation('name', 'en') ?? '—'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state === 'published' ? 'Publicat' : 'Draft'),
                Tables\Columns\TextColumn::make('updated_at')->label('Modificat')->since()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Publicat']),
            ])
            ->recordActions([
                Action::make('designer')->label('Designer')->icon('heroicon-o-pencil-square')->color('primary')
                    ->url(fn ($record) => static::getUrl('designer', ['record' => $record])),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListSeatingLayouts::route('/'),
            'create'   => Pages\CreateSeatingLayout::route('/create'),
            'edit'     => Pages\EditSeatingLayout::route('/{record}/edit'),
            'designer' => Pages\DesignerSeatingLayout::route('/{record}/designer'),
            'preview'  => Pages\PreviewSeatingLayout::route('/{record}/preview'),
        ];
    }
}

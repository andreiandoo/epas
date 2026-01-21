<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages;
use App\Models\Seating\SeatingLayout;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Support\Enums\FontWeight;
use BackedEnum;
use UnitEnum;

class SeatingLayoutResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = SeatingLayout::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Seating Layouts';
    protected static ?string $modelLabel = 'Seating Layout';
    protected static ?string $pluralModelLabel = 'Seating Layouts';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        // Return layouts belonging to this marketplace
        // Use try-catch in case migration hasn't run yet
        try {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes() // Remove TenantScope for marketplace access
                ->where('marketplace_client_id', $marketplace?->id);
        } catch (\Exception $e) {
            // If column doesn't exist yet, return empty query
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0'); // Return empty result
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Check if migration has run by checking if column exists
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('seating_layouts', 'marketplace_client_id');
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            // Hidden marketplace_client_id
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    // ============================================================
                    // SEARCH EXISTING LAYOUTS (only on create page)
                    // ============================================================
                    SC\Section::make('Caută layout-uri existente')
                        ->icon('heroicon-o-magnifying-glass')
                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30'])
                        ->visible(fn ($operation) => $operation === 'create')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Select::make('search_existing_layout')
                                ->label('Caută o hartă existentă în biblioteca Tixello')
                                ->placeholder('Scrie numele layout-ului sau al locației...')
                                ->searchable()
                                ->prefixIcon('heroicon-o-magnifying-glass')
                                ->getOptionLabelUsing(function ($value) use ($marketplace): ?string {
                                    if (!$value) return null;
                                    $layout = SeatingLayout::withoutGlobalScopes()->with('venue')->find($value);
                                    if (!$layout) return null;

                                    $venueName = $layout->venue
                                        ? ($layout->venue->getTranslation('name', 'ro') ?? $layout->venue->getTranslation('name', 'en') ?? '')
                                        : '';
                                    $venueInfo = $venueName ? " - {$venueName}" : '';
                                    $sectionsCount = $layout->sections()->count();
                                    $seatsInfo = " ({$sectionsCount} secțiuni)";

                                    return $layout->name . $venueInfo . $seatsInfo;
                                })
                                ->getSearchResultsUsing(function (string $search) use ($marketplace): array {
                                    if (strlen($search) < 2) {
                                        return [];
                                    }

                                    // Normalize search (remove diacritics)
                                    $normalizedSearch = mb_strtolower($search);
                                    $diacritics = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't'];
                                    $normalizedSearch = strtr($normalizedSearch, $diacritics);

                                    return SeatingLayout::query()
                                        ->withoutGlobalScopes() // Access all layouts
                                        ->with('venue')
                                        ->where(function (Builder $q) use ($normalizedSearch, $search) {
                                            // Search by layout name
                                            $q->where('name', 'like', "%{$normalizedSearch}%")
                                                ->orWhere('name', 'like', "%" . mb_strtolower($search) . "%");
                                        })
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(function (SeatingLayout $layout) use ($marketplace) {
                                            $venueName = $layout->venue
                                                ? ($layout->venue->getTranslation('name', 'ro') ?? $layout->venue->getTranslation('name', 'en') ?? '')
                                                : '';
                                            $venueInfo = $venueName ? " - {$venueName}" : '';
                                            $sectionsCount = $layout->sections()->count();
                                            $seatsInfo = " ({$sectionsCount} secțiuni)";

                                            // Show different status based on ownership
                                            if ($layout->marketplace_client_id === $marketplace?->id) {
                                                $status = ' [Deja în lista ta]';
                                            } elseif ($layout->marketplace_client_id) {
                                                $status = ' [Aparține altui marketplace]';
                                            } else {
                                                $status = '';
                                            }
                                            return [$layout->id => $layout->name . $venueInfo . $seatsInfo . $status];
                                        })
                                        ->toArray();
                                })
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($state, SSet $set) {
                                    $set('selected_layout_id', $state);
                                })
                                ->hintIcon('heroicon-o-information-circle', tooltip: 'Selectează un layout pentru a vedea detaliile și opțiunea de adăugare ca partener')
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('selected_layout_id'),

                            // Show selected layout details and add button
                            Forms\Components\Placeholder::make('layout_preview')
                                ->label('')
                                ->visible(fn (SGet $get) => !empty($get('search_existing_layout')))
                                ->content(function (SGet $get) use ($marketplace) {
                                    $layoutId = $get('search_existing_layout');
                                    if (!$layoutId) return '';

                                    $layout = SeatingLayout::withoutGlobalScopes()->with(['venue', 'sections'])->find($layoutId);
                                    if (!$layout) return '';

                                    $venueName = $layout->venue
                                        ? ($layout->venue->getTranslation('name', 'ro') ?? $layout->venue->getTranslation('name', 'en') ?? '-')
                                        : '-';
                                    $sectionsCount = $layout->sections->count();
                                    $totalSeats = $layout->sections->sum(fn ($s) => $s->seats()->count());
                                    $status = $layout->status === 'published' ? 'Publicat' : 'Draft';
                                    $canvas = "{$layout->canvas_w}x{$layout->canvas_h}";

                                    // Determine status based on ownership
                                    if ($layout->marketplace_client_id === $marketplace?->id) {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:text-blue-400 dark:bg-blue-900/30">Deja în lista ta</span>';
                                    } elseif ($layout->marketplace_client_id) {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full dark:text-yellow-400 dark:bg-yellow-900/30">Aparține altui marketplace</span>';
                                    } else {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full dark:text-green-400 dark:bg-green-900/30">Disponibil pentru parteneriat</span>';
                                    }

                                    return new HtmlString("
                                        <div class='p-4 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700'>
                                            <div class='flex items-start justify-between mb-3'>
                                                <h4 class='text-lg font-semibold text-gray-900 dark:text-white'>{$layout->name}</h4>
                                                {$statusBadge}
                                            </div>
                                            <div class='grid grid-cols-4 gap-4 text-sm'>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Locație:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$venueName}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Secțiuni:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$sectionsCount}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Total locuri:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$totalSeats}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Canvas:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$canvas}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ");
                                })
                                ->columnSpanFull(),

                            SC\Actions::make([
                                Action::make('add_as_partner')
                                    ->label('Importa model harta')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('success')
                                    ->size('lg')
                                    ->visible(function (SGet $get) use ($marketplace) {
                                        $layoutId = $get('search_existing_layout');
                                        if (!$layoutId) return false;
                                        $layout = SeatingLayout::withoutGlobalScopes()->find($layoutId);
                                        return $layout && is_null($layout->marketplace_client_id);
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading('Importa model harta')
                                    ->modalDescription('Acest layout va fi importat în lista ta. Vei putea să îl folosești pentru evenimentele tale.')
                                    ->action(function (SGet $get) use ($marketplace) {
                                        $layoutId = $get('search_existing_layout');
                                        $layout = SeatingLayout::withoutGlobalScopes()->find($layoutId);

                                        if (!$layout) {
                                            Notification::make()
                                                ->title('Eroare')
                                                ->body('Layout-ul nu a fost găsit.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        if ($layout->marketplace_client_id) {
                                            Notification::make()
                                                ->title('Eroare')
                                                ->body('Acest layout aparține deja unui alt marketplace.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $layout->update([
                                            'marketplace_client_id' => $marketplace?->id,
                                            'is_partner' => true,
                                        ]);

                                        Notification::make()
                                            ->title('Layout importat')
                                            ->body('"' . $layout->name . '" a fost importat cu succes.')
                                            ->success()
                                            ->send();

                                        // Redirect to the layouts list
                                        return redirect(static::getUrl('index'));
                                    }),
                            ])->visible(fn (SGet $get) => !empty($get('search_existing_layout'))),
                        ]),

                    // Separator between search and create new (only on create page)
                    Forms\Components\Placeholder::make('or_create_new')
                        ->hiddenLabel()
                        ->visible(fn ($operation) => $operation === 'create')
                        ->content(new HtmlString('
                            <div class="flex items-center gap-4 py-4">
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">sau creează un layout nou mai jos</span>
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                        '))
                        ->columnSpanFull(),

                    // ============================================================
                    // CREATE NEW LAYOUT FORM
                    // ============================================================
                    SC\Section::make('Informații de bază')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Select::make('venue_id')
                                ->label('Locație')
                                ->relationship('venue', 'name', fn (Builder $query) => $query->where('marketplace_client_id', static::getMarketplaceClient()?->id))
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    $locale = app()->getLocale();
                                    $name = $record->getTranslation('name', $locale) ?? $record->getTranslation('name', 'en') ?? 'Unnamed Venue';
                                    $city = $record->city ?? null;
                                    return $city ? "{$name} ({$city})" : $name;
                                })
                                ->searchable(['name', 'city'])
                                ->preload()
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('name')
                                ->label('Nume model hartă')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),

                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'draft' => 'Draft',
                                    'published' => 'Publicat',
                                ])
                                ->default('draft')
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('notes')
                                ->label('Note')
                                ->maxLength(1000)
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    SC\Section::make('Setări Canvas')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\TextInput::make('canvas_w')
                                ->label('Lățime Canvas (px)')
                                ->required()
                                ->numeric()
                                ->default(config('seating.canvas.default_width', 1920))
                                ->minValue(config('seating.canvas.min_width', 800))
                                ->maxValue(config('seating.canvas.max_width', 4096))
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('canvas_h')
                                ->label('Înălțime Canvas (px)')
                                ->required()
                                ->numeric()
                                ->default(config('seating.canvas.default_height', 1080))
                                ->minValue(config('seating.canvas.min_height', 600))
                                ->maxValue(config('seating.canvas.max_height', 4096))
                                ->columnSpan(1),

                            Forms\Components\FileUpload::make('background_image_path')
                                ->label('Imagine de fundal')
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                                ->disk('public')
                                ->directory('seating/backgrounds')
                                ->maxSize(10240)
                                ->preserveFilenames()
                                ->imagePreviewHeight('250')
                                ->hintIcon('heroicon-o-information-circle', tooltip: 'Plan opțional al locației sau imagine de fundal (max 10MB)')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // Partner notes (internal)
                    SC\Section::make('Note interne')
                        ->description('Note interne despre acest layout (nu sunt vizibile public)')
                        ->icon('heroicon-o-lock-closed')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Textarea::make('partner_notes')
                                ->label('Note')
                                ->placeholder('Note despre parteneriat, configurări speciale, etc.')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    // Metadata (doar pe Edit)
                    SC\Section::make('Metadate')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('version')
                                ->label('Versiune')
                                ->content(fn ($record) => $record?->version ?? 1),

                            Forms\Components\Placeholder::make('created_info')
                                ->label('Creat')
                                ->content(function ($record) {
                                    $date = $record?->created_at?->format('d.m.Y H:i') ?? '-';
                                    $user = $record?->creator?->name ?? '-';
                                    return "{$date} de {$user}";
                                }),

                            Forms\Components\Placeholder::make('updated_info')
                                ->label('Actualizat')
                                ->content(function ($record) {
                                    $date = $record?->updated_at?->format('d.m.Y H:i') ?? '-';
                                    $user = $record?->updater?->name ?? ($record?->creator?->name ?? '-');
                                    return "{$date} de {$user}";
                                }),
                        ]),

                    // Statistici (doar pe Edit)
                    SC\Section::make('Statistici')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('stats')
                                ->hiddenLabel()
                                ->content(function (?SeatingLayout $record) {
                                    if (!$record) return '';

                                    $sectionsCount = $record->sections()->count();
                                    $totalSeats = $record->sections->sum(fn ($s) => $s->seats()->count());
                                    $canvas = "{$record->canvas_w}x{$record->canvas_h}";

                                    return new HtmlString("
                                        <div class='space-y-2 text-sm'>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Secțiuni:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$sectionsCount}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Total locuri:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$totalSeats}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Canvas:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$canvas}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Quick Actions (doar pe Edit)
                    SC\Section::make('Acțiuni rapide')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            SC\Actions::make([
                                Action::make('open_designer')
                                    ->label('Deschide Designer')
                                    ->icon('heroicon-o-paint-brush')
                                    ->color('primary')
                                    ->url(fn (?SeatingLayout $record) => $record ? static::getUrl('designer', ['record' => $record]) : null),
                            ]),
                        ]),

                    // Partner status (only on edit)
                    SC\Section::make('Status partener')
                        ->icon('heroicon-o-user-group')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Toggle::make('is_partner')
                                ->label('Layout partener')
                                ->helperText('Layout importat din Core')
                                ->disabled(),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->url(fn (SeatingLayout $record) => static::getUrl('designer', ['record' => $record])),

                Tables\Columns\TextColumn::make('venue_name')
                    ->label('Locație')
                    ->state(fn (SeatingLayout $record) => $record->venue
                        ? ($record->venue->getTranslation('name', 'ro') ?: $record->venue->getTranslation('name', 'en'))
                        : '-')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('venue', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_seats')
                    ->label('Locuri')
                    ->state(fn (SeatingLayout $record) => $record->sections->sum(fn ($s) => $s->seats()->count()))
                    ->sortable(query: fn ($query, $direction) => $query)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'published' => 'Publicat',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('canvas_w')
                    ->label('Canvas')
                    ->formatStateUsing(fn ($record) => "{$record->canvas_w}x{$record->canvas_h}")
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Secțiuni')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_partner')
                    ->label('Partener')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Publicat',
                    ]),

                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label('Doar parteneri'),

                Tables\Filters\SelectFilter::make('venue')
                    ->label('Locație')
                    ->relationship('venue', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en') ?? 'Unnamed')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('designer')
                    ->label('Designer')
                    ->icon('heroicon-o-paint-brush')
                    ->color('primary')
                    ->url(fn (SeatingLayout $record) => static::getUrl('designer', ['record' => $record])),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeatingLayouts::route('/'),
            'create' => Pages\CreateSeatingLayout::route('/create'),
            'edit' => Pages\EditSeatingLayout::route('/{record}/edit'),
            'designer' => Pages\DesignerSeatingLayout::route('/{record}/designer'),
        ];
    }
}

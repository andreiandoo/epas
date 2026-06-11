<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use App\Models\MarketplaceEvent;
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

class EventSeatingLayoutResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = EventSeatingLayout::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Event Seating';
    protected static ?string $modelLabel = 'Event Seating Layout';
    protected static ?string $pluralModelLabel = 'Event Seating Layouts';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        // Return event seating layouts belonging to this marketplace
        try {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->where('marketplace_client_id', $marketplace?->id)
                ->with(['marketplaceEvent', 'baseLayout']);
        } catch (\Exception $e) {
            // If column doesn't exist yet, return empty query
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Check if migration has run by checking if column exists
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('event_seating_layouts', 'marketplace_client_id');
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
                    // SEARCH EXISTING EVENT SEATING LAYOUTS (only on create page)
                    // ============================================================
                    SC\Section::make('Caută configurații existente')
                        ->description('Caută în toate configurările de locuri pentru evenimente. Dacă găsești configurația dorită, o poți adăuga ca partener.')
                        ->icon('heroicon-o-magnifying-glass')
                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30'])
                        ->visible(fn ($operation) => $operation === 'create')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Select::make('search_existing')
                                ->label('Caută o configurație existentă')
                                ->placeholder('Scrie numele evenimentului...')
                                ->searchable()
                                ->prefixIcon('heroicon-o-magnifying-glass')
                                ->getSearchResultsUsing(function (string $search) use ($marketplace): array {
                                    if (strlen($search) < 2) {
                                        return [];
                                    }

                                    // Search in all event seating layouts
                                    return EventSeatingLayout::query()
                                        ->withoutGlobalScopes()
                                        ->with(['event', 'marketplaceEvent', 'baseLayout'])
                                        ->where(function (Builder $q) use ($search) {
                                            // Search by event name (Core events)
                                            $q->whereHas('event', function (Builder $q2) use ($search) {
                                                $q2->where('title', 'like', "%{$search}%");
                                            })
                                            // Or by marketplace event name
                                            ->orWhereHas('marketplaceEvent', function (Builder $q2) use ($search) {
                                                $q2->where('name', 'like', "%{$search}%");
                                            })
                                            // Or by layout name
                                            ->orWhereHas('baseLayout', function (Builder $q2) use ($search) {
                                                $q2->where('name', 'like', "%{$search}%");
                                            });
                                        })
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(function (EventSeatingLayout $esl) use ($marketplace) {
                                            // Determine event name
                                            $eventName = $esl->marketplaceEvent?->name
                                                ?? ($esl->event?->getTranslation('title', app()->getLocale()) ?? $esl->event?->getTranslation('title', 'en'))
                                                ?? "Event #{$esl->event_id}";
                                            $layoutName = $esl->baseLayout?->name ?? 'Unknown layout';
                                            $statusLabel = match ($esl->status) {
                                                'draft' => 'Draft',
                                                'published' => 'Publicat',
                                                'archived' => 'Arhivat',
                                                default => $esl->status,
                                            };

                                            // Ownership status
                                            if ($esl->marketplace_client_id === $marketplace?->id) {
                                                $status = ' [Deja în lista ta]';
                                            } elseif ($esl->marketplace_client_id) {
                                                $status = ' [Aparține altui marketplace]';
                                            } else {
                                                $status = '';
                                            }

                                            return [$esl->id => "{$eventName} - {$layoutName} ({$statusLabel}){$status}"];
                                        })
                                        ->toArray();
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set) {
                                    $set('selected_esl_id', $state);
                                })
                                ->hintIcon('heroicon-o-information-circle', tooltip: 'Selectează o configurație pentru a vedea detaliile')
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('selected_esl_id'),

                            // Show selected details
                            Forms\Components\Placeholder::make('esl_preview')
                                ->label('')
                                ->visible(fn (SGet $get) => !empty($get('search_existing')))
                                ->content(function (SGet $get) use ($marketplace) {
                                    $eslId = $get('search_existing');
                                    if (!$eslId) return '';

                                    $esl = EventSeatingLayout::withoutGlobalScopes()
                                        ->with(['event', 'marketplaceEvent', 'baseLayout'])
                                        ->find($eslId);
                                    if (!$esl) return '';

                                    $eventName = $esl->marketplaceEvent?->name
                                        ?? ($esl->event?->getTranslation('title', 'ro') ?? $esl->event?->getTranslation('title', 'en'))
                                        ?? "Event #{$esl->event_id}";
                                    $layoutName = $esl->baseLayout?->name ?? '-';
                                    $statusLabel = match ($esl->status) {
                                        'draft' => 'Draft',
                                        'published' => 'Publicat',
                                        'archived' => 'Arhivat',
                                        default => $esl->status,
                                    };
                                    $counts = $esl->getSeatStatusCounts();

                                    // Ownership status badge
                                    if ($esl->marketplace_client_id === $marketplace?->id) {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:text-blue-400 dark:bg-blue-900/30">Deja în lista ta</span>';
                                    } elseif ($esl->marketplace_client_id) {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full dark:text-yellow-400 dark:bg-yellow-900/30">Aparține altui marketplace</span>';
                                    } else {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full dark:text-green-400 dark:bg-green-900/30">Disponibil pentru parteneriat</span>';
                                    }

                                    return new HtmlString("
                                        <div class='p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'>
                                            <div class='flex justify-between items-start mb-3'>
                                                <h4 class='text-lg font-semibold text-gray-900 dark:text-white'>{$eventName}</h4>
                                                {$statusBadge}
                                            </div>
                                            <div class='grid grid-cols-4 gap-4 text-sm'>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Layout:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$layoutName}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Status:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$statusLabel}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Total locuri:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$counts['total']}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Disponibile:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$counts['available']}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ");
                                })
                                ->columnSpanFull(),

                            SC\Actions::make([
                                Action::make('add_as_partner')
                                    ->label('Adaugă ca partener')
                                    ->icon('heroicon-o-plus-circle')
                                    ->color('success')
                                    ->size('lg')
                                    ->visible(function (SGet $get) use ($marketplace) {
                                        $eslId = $get('search_existing');
                                        if (!$eslId) return false;
                                        $esl = EventSeatingLayout::withoutGlobalScopes()->find($eslId);
                                        return $esl && is_null($esl->marketplace_client_id);
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading('Adaugă configurația ca partener')
                                    ->modalDescription('Această configurație de locuri va fi adăugată în lista ta.')
                                    ->action(function (SGet $get) use ($marketplace) {
                                        $eslId = $get('search_existing');
                                        $esl = EventSeatingLayout::withoutGlobalScopes()->find($eslId);

                                        if (!$esl) {
                                            Notification::make()
                                                ->title('Eroare')
                                                ->body('Configurația nu a fost găsită.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        if ($esl->marketplace_client_id) {
                                            Notification::make()
                                                ->title('Eroare')
                                                ->body('Această configurație aparține deja unui alt marketplace.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $esl->update([
                                            'marketplace_client_id' => $marketplace?->id,
                                            'is_partner' => true,
                                        ]);

                                        Notification::make()
                                            ->title('Configurație adăugată')
                                            ->body('Configurația a fost adăugată ca partener.')
                                            ->success()
                                            ->send();

                                        return redirect(static::getUrl('index'));
                                    }),
                            ])->visible(fn (SGet $get) => !empty($get('search_existing'))),
                        ]),

                    // Separator
                    Forms\Components\Placeholder::make('or_create_new')
                        ->hiddenLabel()
                        ->visible(fn ($operation) => $operation === 'create')
                        ->content(new HtmlString('
                            <div class="flex items-center gap-4 py-4">
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">sau creează o configurație nouă mai jos</span>
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                        '))
                        ->columnSpanFull(),

                    // ============================================================
                    // CREATE NEW EVENT SEATING FORM
                    // ============================================================
                    SC\Section::make('Eveniment & Layout')
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            Forms\Components\Select::make('marketplace_event_id')
                                ->label('Eveniment')
                                ->options(fn () => MarketplaceEvent::query()
                                    ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                    ->orderBy('starts_at', 'desc')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn ($event) => [
                                        $event->id => $event->name . ' (' . $event->starts_at?->format('d.m.Y') . ')'
                                    ])
                                    ->all()
                                )
                                ->required()
                                ->searchable()
                                ->helperText('Selectează evenimentul pentru care configurezi locurile')
                                ->columnSpan(1),

                            Forms\Components\Select::make('layout_id')
                                ->label('Layout de bază')
                                ->options(fn () => SeatingLayout::query()
                                    ->withoutGlobalScopes()
                                    ->where(function ($q) {
                                        $q->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                            ->orWhereNull('marketplace_client_id');
                                    })
                                    ->where('status', 'published')
                                    ->pluck('name', 'id')
                                    ->all()
                                )
                                ->required()
                                ->searchable()
                                ->helperText('Alege un layout publicat ca șablon')
                                ->columnSpan(1),

                            Forms\Components\Select::make('status')
                                ->options([
                                    'draft' => 'Draft',
                                    'published' => 'Publicat',
                                    'archived' => 'Arhivat',
                                ])
                                ->default('draft')
                                ->required()
                                ->helperText('Doar configurațiile publicate sunt vizibile public')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    SC\Section::make('Publicare')
                        ->icon('heroicon-o-clock')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\DateTimePicker::make('published_at')
                                ->label('Publicat la')
                                ->native(false)
                                ->helperText('Se setează automat când statusul devine "Publicat"')
                                ->disabled()
                                ->columnSpan(1),

                            Forms\Components\DateTimePicker::make('archived_at')
                                ->label('Arhivat la')
                                ->native(false)
                                ->helperText('Se setează automat când statusul devine "Arhivat"')
                                ->disabled()
                                ->columnSpan(1),
                        ])
                        ->columns(2),

                    // Partner notes
                    SC\Section::make('Note interne')
                        ->description('Note interne (nu sunt vizibile public)')
                        ->icon('heroicon-o-lock-closed')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Textarea::make('partner_notes')
                                ->label('Note')
                                ->placeholder('Note despre această configurație...')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    // Seat inventory (only on edit)
                    SC\Section::make('Inventar locuri')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('seat_stats')
                                ->hiddenLabel()
                                ->content(function (?EventSeatingLayout $record) {
                                    if (!$record) return '';

                                    $counts = $record->getSeatStatusCounts();

                                    return new HtmlString("
                                        <div class='space-y-2 text-sm'>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Total:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$counts['total']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Disponibile:</span>
                                                <span class='font-semibold text-green-600 dark:text-green-400'>{$counts['available']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Rezervate:</span>
                                                <span class='font-semibold text-yellow-600 dark:text-yellow-400'>{$counts['held']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Vândute:</span>
                                                <span class='font-semibold text-blue-600 dark:text-blue-400'>{$counts['sold']}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Blocate:</span>
                                                <span class='font-semibold text-red-600 dark:text-red-400'>{$counts['blocked']}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Metadata (only on edit)
                    SC\Section::make('Metadate')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('created_at')
                                ->label('Creat la')
                                ->content(fn ($record) => $record?->created_at?->format('d.m.Y H:i') ?? '-'),

                            Forms\Components\Placeholder::make('updated_at')
                                ->label('Actualizat la')
                                ->content(fn ($record) => $record?->updated_at?->format('d.m.Y H:i') ?? '-'),
                        ]),

                    // Partner status
                    SC\Section::make('Status partener')
                        ->icon('heroicon-o-user-group')
                        ->compact()
                        ->schema([
                            Forms\Components\Toggle::make('is_partner')
                                ->label('Configurație partener')
                                ->helperText('Importată din Core')
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
                Tables\Columns\TextColumn::make('marketplaceEvent.name')
                    ->label('Eveniment')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->url(fn (EventSeatingLayout $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('baseLayout.name')
                    ->label('Layout')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'archived' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'published' => 'Publicat',
                        'archived' => 'Arhivat',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('seats.total')
                    ->label('Total')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['total'] ?? 0)
                    ->sortable(),

                Tables\Columns\TextColumn::make('seats.available')
                    ->label('Disponibile')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['available'] ?? 0)
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('seats.sold')
                    ->label('Vândute')
                    ->formatStateUsing(fn (EventSeatingLayout $record) => $record->getSeatStatusCounts()['sold'] ?? 0)
                    ->badge()
                    ->color('info'),

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
                        'archived' => 'Arhivat',
                    ]),

                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label('Doar parteneri'),

                Tables\Filters\SelectFilter::make('marketplace_event')
                    ->label('Eveniment')
                    ->relationship('marketplaceEvent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
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
            'index' => Pages\ListEventSeatingLayouts::route('/'),
            'create' => Pages\CreateEventSeatingLayout::route('/create'),
            'edit' => Pages\EditEventSeatingLayout::route('/{record}/edit'),
        ];
    }
}

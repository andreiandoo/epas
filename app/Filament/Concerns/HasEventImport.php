<?php

namespace App\Filament\Concerns;

use App\Models\Artist;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\MarketplaceEventCategory;
use App\Models\Venue;
use App\Services\EventImport\DTOs\ImportedRow;
use App\Services\EventImport\EventImportService;
use App\Services\EventImport\Parsers\IabiletParser;
use App\Services\EventImport\Parsers\ImportParserInterface;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Livewire\WithFileUploads;

trait HasEventImport
{
    use WithFileUploads;

    // Stage tracking
    public int $stage = 1;

    // Stage 1: Event setup form
    public ?array $eventFormData = [
        'import_existing' => false,
        'existing_event_id' => null,
        'external_platform_name' => null,
        'import_source' => 'iabilet',
        'event_status' => 'completed',
        'title' => null,
        'event_date' => null,
        'start_time' => null,
        'end_time' => null,
        'duration_mode' => 'single_day',
        'range_start_date' => null,
        'range_end_date' => null,
        'description' => null,
        'venue_id' => null,
        'venue_city' => null,
        'venue_address' => null,
        'website_url' => null,
        'facebook_url' => null,
        'artist_ids' => [],
        'marketplace_event_category_id' => null,
        'event_type_ids' => [],
        'event_genre_ids' => [],
        'commission_rate' => 0,
        'commission_mode' => 'included',
        'marketplace_organizer_id' => null,
        'tenant_id' => null,
    ];

    // Stage 2: File upload & preview
    public $uploadedFile = null;
    public ?string $storedFilePath = null;
    public array $csvHeaders = [];
    public array $csvPreview = [];
    public int $csvTotalRows = 0;
    public array $discoveredTicketTypes = [];

    // Stage 3: Processing
    public bool $isProcessing = false;
    public int $processedOrders = 0;
    public int $totalOrders = 0;
    public ?string $processingStatus = null;

    // Stage 4: Results
    public ?array $importResults = null;

    /**
     * Available import parsers.
     * @return ImportParserInterface[]
     */
    protected function getParsers(): array
    {
        return [
            'iabilet' => new IabiletParser(),
        ];
    }

    protected function getSourceOptions(): array
    {
        $options = [];
        foreach ($this->getParsers() as $key => $parser) {
            $options[$key] = $parser->sourceLabel();
        }
        return $options;
    }

    /**
     * Get tenant_id for the current context.
     * Override in panel-specific pages.
     */
    protected function resolveImportTenantId(): ?int
    {
        return null;
    }

    /**
     * Override in panel-specific pages to prepend extra fields (e.g. tenant selector).
     */
    protected function getExtraEventFormFields(): array
    {
        return [];
    }

    public function eventSetupForm(Schema $schema): Schema
    {
        $fields = $this->getExtraEventFormFields();

        return $schema
            ->statePath('eventFormData')
            ->schema(array_merge($fields, [

                // Import existing event toggle
                Forms\Components\Toggle::make('import_existing')
                    ->label('Import pe eveniment existent')
                    ->helperText('Activează pentru a importa comenzi/bilete pe un eveniment deja creat în Tixello.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                        if (!$state) {
                            $set('existing_event_id', null);
                            $set('external_platform_name', null);
                        }
                    }),

                Forms\Components\Select::make('existing_event_id')
                    ->label('Selectează evenimentul')
                    ->searchable()
                    ->preload()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('import_existing'))
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('import_existing'))
                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $organizerId = $get('marketplace_organizer_id') ?? null;
                        $query = \App\Models\Event::query();

                        if ($organizerId) {
                            $query->where('marketplace_organizer_id', $organizerId);
                        }

                        return $query->orderByDesc('event_date')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(function ($e) {
                                $title = is_array($e->title) ? ($e->title['ro'] ?? reset($e->title) ?? '') : ($e->title ?? '');
                                $date = $e->event_date?->format('d.m.Y') ?? 'TBD';
                                $status = $e->status ?? '';
                                return [$e->id => "{$title} ({$date}) [{$status}]"];
                            });
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                        if (!$state) return;
                        $event = \App\Models\Event::with(['venue', 'eventTypes', 'eventGenres'])->find($state);
                        if (!$event) return;

                        $title = is_array($event->title) ? ($event->title['ro'] ?? '') : ($event->title ?? '');
                        $set('title', $title);
                        $set('event_date', $event->event_date?->format('Y-m-d'));
                        $set('start_time', $event->start_time);
                        $set('end_time', $event->end_time);
                        $set('duration_mode', $event->duration_mode ?? 'single_day');
                        $set('range_start_date', $event->range_start_date?->format('Y-m-d'));
                        $set('range_end_date', $event->range_end_date?->format('Y-m-d'));
                        $set('venue_id', $event->venue_id);
                        $set('description', is_array($event->description) ? ($event->description['ro'] ?? '') : ($event->description ?? ''));
                        $set('event_type_ids', $event->eventTypes->pluck('id')->toArray());
                        $set('event_genre_ids', $event->eventGenres->pluck('id')->toArray());
                        $set('marketplace_event_category_id', $event->marketplace_event_category_id);
                    }),

                Forms\Components\TextInput::make('external_platform_name')
                    ->label('Numele platformei externe')
                    ->placeholder('ex: iaBilet, Eventim, MyTicket')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('import_existing'))
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('import_existing'))
                    ->maxLength(100),

                SC\Grid::make(2)->schema([
                    Forms\Components\Select::make('import_source')
                        ->label('Sursă import')
                        ->options($this->getSourceOptions())
                        ->required()
                        ->default('iabilet')
                        ->native(false),

                    Forms\Components\Select::make('event_status')
                        ->label('Status eveniment')
                        ->options([
                            'completed' => 'Încheiat',
                            'ongoing' => 'În derulare',
                        ])
                        ->required()
                        ->default('completed')
                        ->native(false),
                ]),

                SC\Section::make('Detalii eveniment')->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Nume eveniment')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('duration_mode')
                        ->label('Tip program')
                        ->options([
                            'single_day' => 'O singură zi',
                            'date_range' => 'Interval de date',
                            'multi_day' => 'Mai multe zile cu program diferit',
                        ])
                        ->default('single_day')
                        ->live()
                        ->native(false),

                    SC\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Data eveniment')
                            ->required()
                            ->native(false),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Ora start')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Ora final')
                            ->seconds(false)
                            ->native(true),
                    ]),

                    SC\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('range_start_date')
                            ->label('Data start interval')
                            ->native(false)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('duration_mode'), ['date_range', 'multi_day'])),
                        Forms\Components\DatePicker::make('range_end_date')
                            ->label('Data final interval')
                            ->native(false)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => in_array($get('duration_mode'), ['date_range', 'multi_day'])),
                    ]),

                    Forms\Components\RichEditor::make('description')
                        ->label('Descriere')
                        ->columnSpanFull(),
                ]),

                SC\Section::make('Locație și artiști')->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Venue')
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return Venue::query()
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro') ?: $v->name) . ($v->city ? " ({$v->city})" : ''),
                                ]);
                        })
                        ->getOptionLabelUsing(function ($value) {
                            $v = Venue::find($value);
                            if (!$v) return "Venue #{$value}";
                            return ($v->getTranslation('name', 'ro') ?: $v->name) . ($v->city ? " ({$v->city})" : '');
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            $term = '%' . mb_strtolower($search) . '%';
                            $isPgsql = \DB::getDriverName() === 'pgsql';

                            return Venue::query()
                                ->where(function ($q) use ($term, $isPgsql) {
                                    if ($isPgsql) {
                                        $q->whereRaw("unaccent(LOWER(name::text)) LIKE unaccent(?)", [$term])
                                          ->orWhereRaw("unaccent(LOWER(COALESCE(city, ''))) LIKE unaccent(?)", [$term]);
                                    } else {
                                        $q->whereRaw("LOWER(name) LIKE ?", [$term])
                                          ->orWhereRaw("LOWER(COALESCE(city, '')) LIKE ?", [$term]);
                                    }
                                })
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro') ?: $v->name) . ($v->city ? " ({$v->city})" : ''),
                                ]);
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nume venue')
                                ->required(),
                            Forms\Components\TextInput::make('city')
                                ->label('Oraș'),
                            Forms\Components\TextInput::make('country')
                                ->label('Țară')
                                ->default('RO'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $venue = Venue::create([
                                'name' => ['ro' => $data['name']],
                                'slug' => \Illuminate\Support\Str::slug($data['name']),
                                'city' => $data['city'] ?? null,
                                'country' => $data['country'] ?? 'RO',
                                'tenant_id' => $this->resolveImportTenantId(),
                            ]);
                            return $venue->id;
                        }),

                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('venue_city')
                            ->label('Oraș eveniment')
                            ->placeholder('Se preia automat de la venue dacă e selectat')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('venue_address')
                            ->label('Adresă eveniment')
                            ->maxLength(500),
                    ]),

                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('website_url')
                            ->label('Website eveniment')
                            ->url()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook eveniment')
                            ->url()
                            ->maxLength(500),
                    ]),

                    Forms\Components\Select::make('artist_ids')
                        ->label('Artiști')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return Artist::query()
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id');
                        })
                        ->getSearchResultsUsing(function (string $search) {
                            $term = '%' . mb_strtolower($search) . '%';
                            $isPgsql = \DB::getDriverName() === 'pgsql';

                            return Artist::query()
                                ->where(function ($q) use ($term, $isPgsql) {
                                    if ($isPgsql) {
                                        $q->whereRaw("unaccent(LOWER(name)) LIKE unaccent(?)", [$term]);
                                    } else {
                                        $q->whereRaw("LOWER(name) LIKE ?", [$term]);
                                    }
                                })
                                ->limit(20)
                                ->pluck('name', 'id');
                        })
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nume artist')
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $artist = Artist::create([
                                'name' => $data['name'],
                                'slug' => \Illuminate\Support\Str::slug($data['name']),
                                'letter' => strtoupper(substr($data['name'], 0, 1)),
                            ]);
                            return $artist->id;
                        }),
                ]),

                SC\Section::make('Taxonomii')->schema([
                    Forms\Components\Select::make('marketplace_event_category_id')
                        ->label('Categorie eveniment')
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return MarketplaceEventCategory::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => $c->getTranslation('name', 'ro') ?: $c->getTranslation('name', 'en') ?: $c->name,
                                ]);
                        }),

                    Forms\Components\Select::make('event_type_ids')
                        ->label('Tip eveniment')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return EventType::all()->mapWithKeys(fn ($t) => [
                                $t->id => $t->getTranslation('name', 'en') ?: $t->getTranslation('name', 'ro'),
                            ]);
                        })
                        ->live(),

                    Forms\Components\Select::make('event_genre_ids')
                        ->label('Gen eveniment')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $typeIds = $get('event_type_ids') ?? [];
                            if (empty($typeIds)) {
                                return EventGenre::all()->mapWithKeys(fn ($g) => [
                                    $g->id => $g->getTranslation('name', 'en') ?: $g->getTranslation('name', 'ro'),
                                ]);
                            }
                            return EventGenre::query()
                                ->whereHas('allowedEventTypes', fn ($q) => $q->whereIn('event_types.id', $typeIds))
                                ->get()
                                ->mapWithKeys(fn ($g) => [
                                    $g->id => $g->getTranslation('name', 'en') ?: $g->getTranslation('name', 'ro'),
                                ]);
                        }),
                ]),

                SC\Section::make('Comision sursă externă')->schema([
                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Rata comision (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100),

                        Forms\Components\Select::make('commission_mode')
                            ->label('Mod comision')
                            ->options([
                                'included' => 'Inclus în prețul biletului',
                                'added_on_top' => 'Adăugat peste prețul biletului',
                            ])
                            ->default('included')
                            ->native(false),
                    ]),
                ]),
            ]));
    }

    public function goToStage2(): void
    {
        $data = $this->eventFormData;

        // Validate required fields
        if (empty($data['title'])) {
            Notification::make()->title('Completează numele evenimentului')->danger()->send();
            return;
        }
        if (empty($data['import_source'])) {
            Notification::make()->title('Selectează sursa de import')->danger()->send();
            return;
        }
        if (empty($data['event_date'])) {
            Notification::make()->title('Selectează data evenimentului')->danger()->send();
            return;
        }

        $this->stage = 2;
    }

    public function goBackToStage1(): void
    {
        $this->stage = 1;
    }

    public function uploadAndPreview(): void
    {
        if (!$this->uploadedFile) {
            Notification::make()->title('Încarcă un fișier CSV sau TSV')->danger()->send();
            return;
        }

        // Store the uploaded file
        $path = $this->uploadedFile->store('imports', 'local');
        $this->storedFilePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        $source = $this->eventFormData['import_source'] ?? 'iabilet';
        $parser = $this->getParsers()[$source] ?? null;

        if (!$parser) {
            Notification::make()->title('Parser necunoscut pentru sursa selectată')->danger()->send();
            return;
        }

        try {
            $allRows = $parser->parse($this->storedFilePath);
        } catch (\Throwable $e) {
            Notification::make()->title('Eroare la parsarea fișierului: ' . $e->getMessage())->danger()->send();
            return;
        }

        if (empty($allRows)) {
            Notification::make()->title('Fișierul nu conține date valide')->danger()->send();
            return;
        }

        $this->csvTotalRows = count($allRows);

        // Preview first 10 rows
        $this->csvPreview = array_map(function (ImportedRow $row) {
            return [
                'order_id' => $row->orderId,
                'order_date' => $row->orderDate,
                'client_name' => $row->clientName,
                'email' => $row->email,
                'ticket_type' => $row->ticketTypeName,
                'seat' => $row->seatLabel,
                'price' => $row->ticketPrice,
                'barcode' => $row->barcode,
                'order_status' => $row->orderStatus,
            ];
        }, array_slice($allRows, 0, 10));

        // Discover ticket types
        $types = [];
        foreach ($allRows as $row) {
            $name = $row->ticketTypeName ?? 'General';
            if (!isset($types[$name])) {
                $types[$name] = ['count' => 0, 'price' => $row->ticketPrice ?? 0];
            }
            $types[$name]['count']++;
        }
        $this->discoveredTicketTypes = $types;

        // Count unique orders
        $orderIds = [];
        foreach ($allRows as $row) {
            if ($row->orderId) {
                $orderIds[$row->orderId] = true;
            }
        }
        $this->totalOrders = count($orderIds) ?: $this->csvTotalRows;

        // Store parsed rows in cache for processing
        Cache::put('event_import_rows_' . session()->getId(), serialize($allRows), 3600);

        Notification::make()
            ->title("Fișier încărcat: {$this->csvTotalRows} bilete, " . count($this->discoveredTicketTypes) . " tipuri bilete")
            ->success()
            ->send();
    }

    public function startProcessing(): void
    {
        $this->stage = 3;
        $this->isProcessing = true;
        $this->processedOrders = 0;
        $this->processingStatus = 'Se procesează...';

        $this->processImport();
    }

    public function processImport(): void
    {
        $cacheKey = 'event_import_rows_' . session()->getId();
        $serialized = Cache::get($cacheKey);

        if (!$serialized) {
            Notification::make()->title('Datele importate au expirat. Reîncarcă fișierul.')->danger()->send();
            $this->stage = 2;
            $this->isProcessing = false;
            return;
        }

        /** @var ImportedRow[] $rows */
        $rows = unserialize($serialized);

        $tenantId = $this->resolveImportTenantId();
        if (!$tenantId) {
            Notification::make()->title('Nu s-a putut determina tenant-ul.')->danger()->send();
            $this->isProcessing = false;
            return;
        }

        $source = $this->eventFormData['import_source'] ?? 'iabilet';

        try {
            $service = new EventImportService();
            $result = $service->process($rows, $this->eventFormData, $tenantId, $source);

            $this->importResults = $result->toArray();
            $this->stage = 4;
            $this->isProcessing = false;

            // Clean up
            Cache::forget($cacheKey);
            if ($this->storedFilePath && file_exists($this->storedFilePath)) {
                @unlink($this->storedFilePath);
            }

            Notification::make()
                ->title('Import finalizat cu succes!')
                ->body("{$result->totalTickets} bilete, {$result->totalOrders} comenzi importate")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->isProcessing = false;
            $this->processingStatus = 'Eroare: ' . $e->getMessage();

            Notification::make()
                ->title('Eroare la import')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetImport(): void
    {
        $this->stage = 1;
        $this->eventFormData = [];
        $this->uploadedFile = null;
        $this->storedFilePath = null;
        $this->csvHeaders = [];
        $this->csvPreview = [];
        $this->csvTotalRows = 0;
        $this->discoveredTicketTypes = [];
        $this->isProcessing = false;
        $this->processedOrders = 0;
        $this->totalOrders = 0;
        $this->processingStatus = null;
        $this->importResults = null;

        Cache::forget('event_import_rows_' . session()->getId());
    }
}
